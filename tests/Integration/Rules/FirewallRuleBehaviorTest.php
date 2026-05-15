<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\PolicyEvidence;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\Firewall as FirewallRuleBuilder,
	Conditions\FirewallPatternFoundInRequest,
	Conditions\RequestTriggersFirewall,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	Responses,
	RuleVO,
	RulesController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Integration coverage for firewall rule/condition behavior:
 * - request matching against enabled patterns
 * - exclusion handling
 * - firewall event audit payload mapping contract
 */
class FirewallRuleBehaviorTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();

		$this->resetFirewallRequestState();
		$this->setFirewallMatcherOptions( [
			'block_sql_queries' => 'Y',
		] );
		$this->requireController()->opts->optSet( 'page_params_whitelist', [] );
	}

	private function resetFirewallRequestState( string $path = '/', array $query = [], array $post = [] ) :void {
		$this->resetIpCaches();

		$con = $this->requireController();

		// Ensure we have a fresh rules controller + condition metadata store per test.
		$con->rules = new RulesController();
		$con->rules->setThisRequest( $con->this_req )->execute();

		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->request_subject_to_shield_restrictions = true;
		$con->this_req->path = $path;
		$con->this_req->request->query = $query;
		$con->this_req->request->post = $post;
	}

	private function setFirewallMatcherOptions( array $overrides = [] ) :void {
		$con = $this->requireController();
		// Keep matcher scope deterministic.
		foreach ( [
			'block_dir_traversal',
			'block_field_truncation',
			'block_sql_queries',
			'block_php_code',
			'block_aggressive',
		] as $opt ) {
			$con->opts->optSet( $opt, 'N' );
		}
		foreach ( $overrides as $opt => $value ) {
			$con->opts->optSet( $opt, $value );
		}
	}

	private function runRequestTriggersFirewall() :bool {
		$con = $this->requireController();
		$condition = new RequestTriggersFirewall();
		$condition->setThisRequest( $con->this_req )
				  ->setParams( [] );
		return $condition->run();
	}

	private function runFirewallRuleConditions() :bool {
		$con = $this->requireController();
		$rule = ( new FirewallRuleBuilder() )->build();
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $con->this_req )
			->process();
	}

	private function responseDefinition( array $responses, string $responseClass ) :array {
		$response = \current( \array_filter(
			$responses,
			static fn( array $resp ) :bool => ( $resp[ 'response' ] ?? '' ) === $responseClass
		) );

		$this->assertNotEmpty( $response, 'Rule must define response: '.$responseClass );
		return $response;
	}

	public function test_enabled_sql_pattern_matches_malicious_request_param() {
		$con = $this->requireController();
		$con->this_req->path = '/products';
		$con->this_req->request->query = [
			'q' => "1 UNION SELECT user_pass FROM wp_users",
		];

		$this->assertTrue( $this->runRequestTriggersFirewall() );

		$meta = $con->rules->getConditionMeta()->getRawData();
		$this->assertArrayHasKey( 'match_pattern', $meta );
		$this->assertArrayHasKey( 'match_request_param', $meta );
		$this->assertArrayHasKey( 'match_request_value', $meta );
		$this->assertArrayHasKey( 'match_category', $meta );
		$this->assertArrayHasKey( 'match_type', $meta );
		$this->assertSame( 'q', $meta[ 'match_request_param' ] );
		$this->assertSame( 'sql_queries', $meta[ 'match_category' ] );
		$this->assertSame( 'regex', $meta[ 'match_type' ] );
	}

	public function test_enabled_directory_traversal_rule_matches_malicious_request_param() {
		$this->setFirewallMatcherOptions( [
			'block_dir_traversal' => 'Y',
		] );
		$this->resetFirewallRequestState( '/download', [
			'file' => '../../etc/passwd',
		] );

		$this->assertTrue( $this->runFirewallRuleConditions() );

		$con = $this->requireController();
		$meta = $con->rules->getConditionMeta()->getRawData();
		$this->assertSame( 'file', $meta[ 'match_request_param' ] ?? '' );
		$this->assertSame( '../../etc/passwd', $meta[ 'match_request_value' ] ?? '' );
		$this->assertSame( 'dir_traversal', $meta[ 'match_category' ] ?? '' );
		$this->assertSame( 'regex', $meta[ 'match_type' ] ?? '' );

		$firewallRule = ( new FirewallRuleBuilder() )->build();
		$this->assertSame( FirewallRuleBuilder::SLUG, $firewallRule->slug );
		$policyGate = $this->responseDefinition( $firewallRule->responses, Responses\RequestPolicyGate::class );
		$this->assertSame( [
			'response' => Responses\RequestPolicyGate::class,
			'params'   => [
				'detector'         => PolicyEvidence::DETECTOR_FIREWALL,
				'legacy_responses' => [
					[
						'response' => Responses\EventFire::class,
						'params'   => [
							'event'            => 'firewall_block',
							'offense_count'    => 1,
							'block'            => false,
							'audit_params_map' => [
								'crawler' => 'matched_useragent',
								'name'    => 'match_name',
								'term'    => 'match_pattern',
								'param'   => 'match_request_param',
								'value'   => 'match_request_value',
								'scan'    => 'match_category',
								'type'    => 'match_type',
							],
						],
					],
					[
						'response' => Responses\FirewallBlock::class,
						'params'   => [],
					],
				],
			],
		], $policyGate );
	}

	public function test_disabled_directory_traversal_rule_does_not_match_request_param() {
		$this->setFirewallMatcherOptions( [
			'block_dir_traversal' => 'N',
		] );
		$this->resetFirewallRequestState( '/download', [
			'file' => '../../etc/passwd',
		] );

		$this->assertFalse( $this->runFirewallRuleConditions() );
	}

	public function test_directory_traversal_rule_respects_request_bypass() {
		$this->setFirewallMatcherOptions( [
			'block_dir_traversal' => 'Y',
		] );
		$this->resetFirewallRequestState( '/download', [
			'file' => '../../etc/passwd',
		] );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;

		$this->assertFalse( $this->runFirewallRuleConditions() );
	}

	public function test_directory_traversal_rule_ignores_non_matching_request_param() {
		$this->setFirewallMatcherOptions( [
			'block_dir_traversal' => 'Y',
		] );
		$this->resetFirewallRequestState( '/download', [
			'file' => 'safe-file.txt',
		] );

		$this->assertFalse( $this->runFirewallRuleConditions() );
	}

	public function test_whitelisted_param_is_excluded_from_firewall_pattern_matching() {
		$con = $this->requireController();
		$con->this_req->path = '/products';
		$con->this_req->request->query = [
			'wp_http_referer' => "1 UNION SELECT user_pass FROM wp_users",
		];

		$this->assertFalse( $this->runRequestTriggersFirewall() );
	}

	public function test_configured_page_parameter_whitelist_excludes_matching_parameter_on_configured_path() {
		$con = $this->requireController();
		$con->opts->optSet( 'page_params_whitelist', [
			'/products,unsafe_param',
		] );
		$this->resetFirewallRequestState( '/products', [
			'unsafe_param' => "1 UNION SELECT user_pass FROM wp_users",
		] );
		$this->setFirewallMatcherOptions( [
			'block_sql_queries' => 'Y',
		] );

		$this->assertFalse( $this->runRequestTriggersFirewall() );
	}

	public function test_configured_page_parameter_whitelist_does_not_exclude_other_parameters() {
		$con = $this->requireController();
		$con->opts->optSet( 'page_params_whitelist', [
			'/products,unsafe_param',
		] );
		$this->resetFirewallRequestState( '/products', [
			'q' => "1 UNION SELECT user_pass FROM wp_users",
		] );
		$this->setFirewallMatcherOptions( [
			'block_sql_queries' => 'Y',
		] );

		$this->assertTrue( $this->runRequestTriggersFirewall() );

		$meta = $con->rules->getConditionMeta()->getRawData();
		$this->assertSame( 'q', $meta[ 'match_request_param' ] ?? '' );
		$this->assertSame( 'sql_queries', $meta[ 'match_category' ] ?? '' );
	}

	public function test_configured_page_parameter_whitelist_does_not_exclude_other_paths() {
		$con = $this->requireController();
		$con->opts->optSet( 'page_params_whitelist', [
			'/products,unsafe_param',
		] );
		$this->resetFirewallRequestState( '/support', [
			'unsafe_param' => "1 UNION SELECT user_pass FROM wp_users",
		] );
		$this->setFirewallMatcherOptions( [
			'block_sql_queries' => 'Y',
		] );

		$this->assertTrue( $this->runRequestTriggersFirewall() );

		$meta = $con->rules->getConditionMeta()->getRawData();
		$this->assertSame( 'unsafe_param', $meta[ 'match_request_param' ] ?? '' );
		$this->assertSame( 'sql_queries', $meta[ 'match_category' ] ?? '' );
	}

	public function test_firewall_event_response_contains_required_audit_fields() {
		$con = $this->requireController();
		$con->this_req->path = '/products';
		$con->this_req->request->query = [
			'q' => "1 UNION SELECT user_pass FROM wp_users",
		];
		$this->assertTrue( $this->runRequestTriggersFirewall() );

		$meta = $con->rules->getConditionMeta()->getRawData();

		$rule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_firewall_event_contract',
			'name'                    => 'Test Firewall Event Contract',
			'conditions'              => fn() => true,
			'condition_meta'          => $meta,
			'responses'               => [
				[
					'response' => Responses\EventFire::class,
					'params'   => [
						'event'            => 'firewall_block',
						'offense_count'    => 1,
						'block'            => false,
						'audit_params_map' => [
							'name'  => 'match_name',
							'term'  => 'match_pattern',
							'param' => 'match_request_param',
							'value' => 'match_request_value',
							'scan'  => 'match_category',
							'type'  => 'match_type',
						],
					],
				]
			],
			'immediate_exec_response' => true,
		] );

		$this->captureShieldEvents();
		( new ResponseProcessor( $rule ) )
			->setThisRequest( $con->this_req )
			->run();

		$events = $this->getCapturedEventsByKey( 'firewall_block' );
		$this->assertNotEmpty( $events, 'Firewall event should fire when event response executes' );

		$auditParams = $events[ 0 ][ 'meta' ][ 'audit_params' ] ?? [];
		foreach ( [ 'name', 'term', 'param', 'value', 'scan', 'type' ] as $required ) {
			$this->assertArrayHasKey( $required, $auditParams, "Missing firewall audit param: {$required}" );
		}
	}

	public function test_firewall_pattern_condition_directly_matches_enabled_regex() {
		$con = $this->requireController();
		$con->this_req->path = '/products';
		$con->this_req->request->query = [
			'q' => "1 UNION SELECT user_pass FROM wp_users",
		];

		$condition = new FirewallPatternFoundInRequest();
		$condition->setThisRequest( $con->this_req )
				  ->setParams( [
					  'pattern'    => '#union.*select#i',
					  'match_type' => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				  ] );

		$this->assertTrue( $condition->run() );
	}
}
