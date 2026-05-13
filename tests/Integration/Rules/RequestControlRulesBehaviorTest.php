<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\BlockPageSiteBlockdown;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\DisableXmlrpc as DisableXmlrpcRuleBuilder,
	Build\Core\RequestIsSiteBlockdownBlocked as SiteBlockdownRuleBuilder,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	Responses,
	RuleVO,
	RulesController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class RequestControlRulesBehaviorTest extends ShieldIntegrationTestCase {

	private array $hookSnapshots = [];

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'disable_xmlrpc',
			'track_xmlrpc',
			'blockdown_cfg',
		] );
		$this->snapshotHook( 'xmlrpc_enabled' );
		$this->snapshotHook( 'xmlrpc_methods' );

		\wp_set_current_user( 0 );
		$this->resetRuleRequestState();
		$this->setRequestControlOptions();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		$this->restoreHooks();
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	private function snapshotHook( string $hook ) :void {
		if ( \array_key_exists( $hook, $this->hookSnapshots ) ) {
			return;
		}

		$this->hookSnapshots[ $hook ] = \array_key_exists( $hook, $GLOBALS[ 'wp_filter' ] ?? [] )
			? $this->cloneFilterSnapshot( $GLOBALS[ 'wp_filter' ][ $hook ] )
			: null;
	}

	private function cloneFilterSnapshot( $filter ) {
		return \is_object( $filter ) ? clone $filter : $filter;
	}

	private function restoreHooks() :void {
		foreach ( $this->hookSnapshots as $hook => $snapshot ) {
			if ( $snapshot === null ) {
				unset( $GLOBALS[ 'wp_filter' ][ $hook ] );
			}
			else {
				$GLOBALS[ 'wp_filter' ][ $hook ] = $snapshot;
			}
		}
	}

	private function resetRuleRequestState(
		string $ip = '203.0.113.60',
		bool $isXmlrpc = false,
		bool $restrictionsEnabled = true
	) :void {
		$this->resetIpCaches();

		$con = $this->requireController();
		$con->rules = new RulesController();
		$con->rules->setThisRequest( $con->this_req )->execute();

		$con->this_req->ip = $ip;
		$this->resetRequestIpStatusCache( $ip );
		$con->this_req->wp_is_xmlrpc = $isXmlrpc;
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->request_subject_to_shield_restrictions = $restrictionsEnabled;
		$con->this_req->is_site_lockdown_active = null;
		$con->this_req->is_ip_whitelisted = null;
	}

	private function resetRequestIpStatusCache( string $ip ) :void {
		$ref = new \ReflectionObject( $this->requireController()->this_req );
		if ( $ref->hasProperty( 'ipStatus' ) ) {
			$prop = $ref->getProperty( 'ipStatus' );
			$prop->setAccessible( true );
			$prop->setValue( $this->requireController()->this_req, new IpRuleStatus( $ip ) );
		}
	}

	private function setRequestControlOptions( array $overrides = [] ) :void {
		$options = \array_merge( [
			'disable_xmlrpc' => 'N',
			'track_xmlrpc'   => '',
			'blockdown_cfg'  => $this->blockdownCfg(),
		], $overrides );

		$opts = $this->requireController()->opts;
		foreach ( $options as $key => $value ) {
			$opts->optSet( $key, $value );
		}
	}

	private function blockdownCfg( array $overrides = [] ) :array {
		return \array_merge( [
			'activated_at' => 0,
			'activated_by' => '',
			'disabled_at'  => 0,
			'exclusions'   => [],
			'whitelist_me' => '',
		], $overrides );
	}

	private function activeBlockdownCfg() :array {
		return $this->blockdownCfg( [
			'activated_at' => Services::Request()->ts(),
			'activated_by' => 'integration',
			'disabled_at'  => 0,
		] );
	}

	private function runRuleConditions( RuleVO $rule ) :bool {
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function responseDefinition( array $responses, string $responseClass, ?callable $matcher = null ) :array {
		$response = \current( \array_filter(
			$responses,
			static fn( array $resp ) :bool => ( $resp[ 'response' ] ?? '' ) === $responseClass
				&& ( $matcher === null || $matcher( $resp ) )
		) );

		$this->assertNotEmpty( $response, 'Rule must define response: '.$responseClass );
		return $response;
	}

	public function test_disable_xmlrpc_rule_matches_enabled_xmlrpc_request_and_defines_contracts() :void {
		$this->setRequestControlOptions( [
			'disable_xmlrpc' => 'Y',
		] );
		$this->resetRuleRequestState( '203.0.113.61', true );

		$rule = ( new DisableXmlrpcRuleBuilder() )->build();

		$this->assertSame( DisableXmlrpcRuleBuilder::SLUG, $rule->slug );
		$this->assertTrue( $this->runRuleConditions( $rule ) );

		$xmlrpcEnabled = $this->responseDefinition(
			$rule->responses,
			Responses\HookAddFilter::class,
			static fn( array $resp ) :bool => ( $resp[ 'params' ][ 'hook' ] ?? '' ) === 'xmlrpc_enabled'
		);
		$this->assertSame( '__return_false', $xmlrpcEnabled[ 'params' ][ 'callback' ] ?? '' );
		$this->assertSame( 1000, (int)( $xmlrpcEnabled[ 'params' ][ 'priority' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $xmlrpcEnabled[ 'params' ][ 'accepted_args' ] ?? -1 ) );

		$xmlrpcMethods = $this->responseDefinition(
			$rule->responses,
			Responses\HookAddFilter::class,
			static fn( array $resp ) :bool => ( $resp[ 'params' ][ 'hook' ] ?? '' ) === 'xmlrpc_methods'
		);
		$this->assertSame( '__return_empty_array', $xmlrpcMethods[ 'params' ][ 'callback' ] ?? '' );
		$this->assertSame( 1000, (int)( $xmlrpcMethods[ 'params' ][ 'priority' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $xmlrpcMethods[ 'params' ][ 'accepted_args' ] ?? -1 ) );

		$eventResponse = $this->responseDefinition( $rule->responses, Responses\EventFire::class );
		$this->assertSame( 'block_xml', $eventResponse[ 'params' ][ 'event' ] ?? '' );
	}

	public function test_disable_xmlrpc_response_registers_filters_and_fires_event() :void {
		unset( $GLOBALS[ 'wp_filter' ][ 'xmlrpc_enabled' ], $GLOBALS[ 'wp_filter' ][ 'xmlrpc_methods' ] );

		$rule = ( new DisableXmlrpcRuleBuilder() )->build();
		$eventResponse = $this->responseDefinition( $rule->responses, Responses\EventFire::class );
		$immediateRule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_disable_xmlrpc_response_contract',
			'name'                    => 'Test Disable XML-RPC Response Contract',
			'conditions'              => fn() => true,
			'responses'               => $rule->responses,
			'immediate_exec_response' => true,
		] );

		$this->captureShieldEvents();
		( new ResponseProcessor( $immediateRule ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$this->assertFalse( \apply_filters( 'xmlrpc_enabled', true ) );
		$this->assertSame( [], \apply_filters( 'xmlrpc_methods', [ 'system.listMethods' => [] ] ) );
		$this->assertSame( 'block_xml', $eventResponse[ 'params' ][ 'event' ] ?? '' );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'block_xml' ) );
	}

	/**
	 * @dataProvider disableXmlrpcNegativeProvider
	 */
	public function test_disable_xmlrpc_rule_does_not_match_outside_owned_disable_contract(
		string $disableXmlrpc,
		string $trackXmlrpc,
		bool $isXmlrpc,
		bool $bypassesRestrictions
	) :void {
		$this->setRequestControlOptions( [
			'disable_xmlrpc' => $disableXmlrpc,
			'track_xmlrpc'   => $trackXmlrpc,
		] );
		$this->resetRuleRequestState( '203.0.113.62', $isXmlrpc );
		$this->requireController()->this_req->request_bypasses_all_restrictions = $bypassesRestrictions;

		$this->assertFalse( $this->runRuleConditions( ( new DisableXmlrpcRuleBuilder() )->build() ) );
	}

	public function disableXmlrpcNegativeProvider() :array {
		return [
			'disabled option'       => [ 'N', '', true, false ],
			'non xmlrpc request'    => [ 'Y', '', false, false ],
			'bypassed request'      => [ 'Y', '', true, true ],
			'bot-track only option' => [ 'N', 'block', true, false ],
		];
	}

	public function test_site_lockdown_rule_matches_active_non_whitelisted_request() :void {
		$this->setRequestControlOptions( [
			'blockdown_cfg' => $this->activeBlockdownCfg(),
		] );
		$this->resetRuleRequestState( '203.0.113.70' );

		$rule = ( new SiteBlockdownRuleBuilder() )->build();

		$this->assertSame( SiteBlockdownRuleBuilder::SLUG, $rule->slug );
		$this->assertTrue(
			( new SiteBlockdownCfg() )
				->applyFromArray( $this->requireController()->comps->opts_lookup->getBlockdownCfg() )
				->isLockdownActive()
		);
		$this->assertTrue( $this->runRuleConditions( $rule ) );

		$displayResponse = $this->responseDefinition( $rule->responses, Responses\DisplayBlockPage::class );
		$this->assertSame( BlockPageSiteBlockdown::SLUG, $displayResponse[ 'params' ][ 'block_page_slug' ] ?? '' );
	}

	public function test_site_lockdown_rule_does_not_match_inactive_lockdown() :void {
		$this->setRequestControlOptions( [
			'blockdown_cfg' => $this->blockdownCfg(),
		] );
		$this->resetRuleRequestState( '203.0.113.71' );

		$this->assertFalse( $this->runRuleConditions( ( new SiteBlockdownRuleBuilder() )->build() ) );
	}

	public function test_site_lockdown_rule_does_not_match_when_shield_restrictions_are_disabled() :void {
		$this->setRequestControlOptions( [
			'blockdown_cfg' => $this->activeBlockdownCfg(),
		] );
		$this->resetRuleRequestState( '203.0.113.72', false, false );

		$this->assertFalse( $this->runRuleConditions( ( new SiteBlockdownRuleBuilder() )->build() ) );
	}

	public function test_site_lockdown_rule_does_not_match_whitelisted_ip() :void {
		$ip = '203.0.113.73';
		$this->setRequestControlOptions( [
			'blockdown_cfg' => $this->activeBlockdownCfg(),
		] );
		TestDataFactory::insertBypass( $ip );
		$this->resetRuleRequestState( $ip );

		$this->assertFalse( $this->runRuleConditions( ( new SiteBlockdownRuleBuilder() )->build() ) );
	}
}
