<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\{
	Controller,
	Plugin\HookTimings
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\{
	IsRequestStatus404,
	MatchRequestPath
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\BuildRuleFromForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\{
	ParseRuleBuilderForm,
	RuleFormBuilderVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumMatchTypes,
	EnumParameters
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\{
	EventFire,
	FirewallBlock,
	HookAddFilter,
	HttpRedirect
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class BuildRuleFromFormTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request'          => new UnitTestRequest(),
			'service_serviceproviders' => new class() extends ServiceProviders {
				public function getProviders_Flat() :array {
					return [];
				}
			},
		] );

		Functions\when( '__' )->returnArg();
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_hook_response_params_are_stored_with_accepted_args_contract() :void {
		$responses = ( new BuildRuleFromFormTestDouble( ( new RuleFormBuilderVO() )->applyFromArray( [
			'name'             => 'machine_contract_rule',
			'description'      => 'machine_contract_rule',
			'conditions_logic' => BuildRuleFromForm::LOGIC_AND,
			'conditions'       => [],
			'checks'           => [
				'checkbox_auto_include_bypass' => [
					'value' => 'N',
				],
			],
			'responses'        => [
				[
					'value'  => HookAddFilter::Slug(),
					'params' => [
						[
							'name'       => 'hook',
							'value'      => 'test_filter_hook',
							'param_type' => EnumParameters::TYPE_STRING,
						],
						[
							'name'       => 'callback',
							'value'      => '__return_false',
							'param_type' => EnumParameters::TYPE_CALLBACK,
						],
						[
							'name'       => 'priority',
							'value'      => '1000',
							'param_type' => EnumParameters::TYPE_INT,
						],
						[
							'name'       => 'args',
							'value'      => '0',
							'param_type' => EnumParameters::TYPE_INT,
						],
					],
				],
			],
		] ) ) )->responsesForTest();

		$this->assertSame( HookAddFilter::class, $responses[ 0 ][ 'response' ] );
		$this->assertArrayHasKey( 'accepted_args', $responses[ 0 ][ 'params' ] );
		$this->assertArrayNotHasKey( 'args', $responses[ 0 ][ 'params' ] );
	}

	public function test_parsed_redirect_response_status_code_is_stored_as_integer() :void {
		$parsedForm = ( new ParseRuleBuilderForm( [
			'rule_name'                     => 'machine_contract_rule',
			'rule_description'              => 'machine_contract_rule',
			'conditions_logic'              => BuildRuleFromForm::LOGIC_AND,
			'checkbox_auto_include_bypass'  => 'N',
			'checkbox_accept_rules_warning' => 'Y',
			'response_1'                    => HttpRedirect::Slug(),
			'response_1_param_redirect_url' => '/',
			'response_1_param_status_code'  => '302',
		] ) )->parseForm();

		$responses = ( new BuildRuleFromFormTestDouble( $parsedForm ) )->responsesForTest();

		$this->assertSame( HttpRedirect::class, $responses[ 0 ][ 'response' ] );
		$this->assertIsInt( $responses[ 0 ][ 'params' ][ 'status_code' ] );
		$this->assertSame( 302, $responses[ 0 ][ 'params' ][ 'status_code' ] );
	}

	public function test_custom_responses_are_deferred_by_default() :void {
		$rule = ( new BuildRuleFromForm( $this->formWith( [], [] ) ) )->build();

		$this->assertFalse( $rule->immediate_exec_response );
	}

	public function test_only_direct_404_custom_rules_run_after_native_404_tracking() :void {
		$rule404 = new BuildRuleFromFormTestDouble( $this->formWith( [
			$this->condition( IsRequestStatus404::Slug() ),
		], [] ) );
		$invertedRule404 = new BuildRuleFromFormTestDouble( $this->formWith( [
			$this->condition( IsRequestStatus404::Slug(), [], EnumLogic::LOGIC_INVERT ),
		], [] ) );
		$pathRule = new BuildRuleFromFormTestDouble( $this->formWith( [
			$this->pathCondition( '/private' ),
		], [] ) );

		$this->assertSame(
			HookTimings::TEMPLATE_REDIRECT_AFTER_WORDPRESS_REDIRECTS + 1,
			$rule404->priorityForTest()
		);
		$this->assertNull( $invertedRule404->priorityForTest() );
		$this->assertNull( $pathRule->priorityForTest() );
	}

	public function test_mixed_or_404_custom_rules_do_not_run_after_native_404_tracking() :void {
		$rule = new BuildRuleFromFormTestDouble( $this->formWith( [
			$this->condition( IsRequestStatus404::Slug() ),
			$this->pathCondition( '/private' ),
		], [], BuildRuleFromForm::LOGIC_OR ) );

		$this->assertNull( $rule->priorityForTest() );
	}

	public function test_firewall_block_gets_canonical_event_with_truthful_custom_audit_data() :void {
		$responses = ( new BuildRuleFromFormTestDouble( $this->formWith(
			[ $this->pathCondition( '/private-area' ) ],
			[ $this->response( FirewallBlock::Slug() ) ]
		) ) )->responsesForTest();

		$this->assertCount( 2, $responses );
		$this->assertSame( EventFire::class, $responses[ 0 ][ 'response' ] );
		$this->assertSame( FirewallBlock::class, $responses[ 1 ][ 'response' ] );

		$params = $responses[ 0 ][ 'params' ];
		$this->assertSame( 'firewall_block', $params[ 'event' ] );
		$this->assertSame( 1, $params[ 'offense_count' ] );
		$this->assertFalse( $params[ 'block' ] );
		$this->assertSame( [ 'value' => 'path' ], $params[ 'audit_params_map' ] );
		$this->assertSame( [
			'name'  => 'machine_contract_rule',
			'term'  => '/private-area',
			'param' => 'path',
			'scan'  => 'custom_rule',
			'type'  => EnumMatchTypes::MATCH_TYPE_EQUALS,
		], $params[ 'audit_params' ] );
	}

	public function test_explicit_firewall_event_is_not_duplicated_or_reordered() :void {
		$responses = ( new BuildRuleFromFormTestDouble( $this->formWith(
			[ $this->pathCondition( '/private-area' ) ],
			[
				$this->response( HookAddFilter::Slug() ),
				$this->response( EventFire::Slug(), [
					$this->param( 'event', 'firewall_block', EnumParameters::TYPE_ENUM ),
				] ),
				$this->response( FirewallBlock::Slug() ),
			]
		) ) )->responsesForTest();

		$firewallEvents = \array_filter( $responses, static function ( array $response ) :bool {
			return ( $response[ 'response' ] ?? '' ) === EventFire::class
				&& ( $response[ 'params' ][ 'event' ] ?? '' ) === 'firewall_block';
		} );

		$this->assertCount( 1, $firewallEvents );
		$this->assertSame( HookAddFilter::class, $responses[ 0 ][ 'response' ] );
		$this->assertSame( EventFire::class, $responses[ 1 ][ 'response' ] );
		$this->assertSame( FirewallBlock::class, $responses[ 2 ][ 'response' ] );
	}

	private function formWith(
		array $conditions,
		array $responses,
		string $conditionsLogic = BuildRuleFromForm::LOGIC_AND
	) :RuleFormBuilderVO {
		return ( new RuleFormBuilderVO() )->applyFromArray( [
			'name'             => 'machine_contract_rule',
			'description'      => 'machine contract description',
			'conditions_logic' => $conditionsLogic,
			'conditions'       => $conditions,
			'checks'           => [
				'checkbox_auto_include_bypass' => [
					'value' => 'N',
				],
			],
			'responses'        => $responses,
		] );
	}

	private function condition( string $slug, array $params = [], string $logic = EnumLogic::LOGIC_ASIS ) :array {
		return [
			'value'  => $slug,
			'invert' => [ 'value' => $logic ],
			'params' => $params,
		];
	}

	private function pathCondition( string $path ) :array {
		return $this->condition( MatchRequestPath::Slug(), [
			$this->param( 'match_type', EnumMatchTypes::MATCH_TYPE_EQUALS, EnumParameters::TYPE_ENUM ),
			$this->param( 'match_path', $path, EnumParameters::TYPE_STRING ),
		] );
	}

	private function response( string $slug, array $params = [] ) :array {
		return [
			'value'  => $slug,
			'params' => $params,
		];
	}

	private function param( string $name, $value, string $type ) :array {
		return [
			'name'       => $name,
			'value'      => $value,
			'param_type' => $type,
		];
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = new class() {
			public string $Name = 'Shield';

			public function getBrandName( string $brand ) :string {
				return $brand;
			}
		};
		$controller->caps = new class() {
			public function canCustomSecurityRules() :bool {
				return true;
			}
		};
		$controller->cfg = (object)[
			'configuration' => new class() {
				public function def( string $key ) :array {
					return [];
				}
			},
		];
		$controller->comps = (object)[
			'events'      => new class() {
				public function getEventNames() :array {
					return [];
				}
			},
			'mfa'         => new class() {
				public function collateMfaProviderClasses() :array {
					return [];
				}
			},
			'opts_lookup' => new class() {
				public function getFirewallParametersWhitelist() :array {
					return [];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class BuildRuleFromFormTestDouble extends BuildRuleFromForm {

	public function responsesForTest() :array {
		return parent::getResponses();
	}

	public function priorityForTest() :?int {
		return parent::getWpHookPriority();
	}
}
