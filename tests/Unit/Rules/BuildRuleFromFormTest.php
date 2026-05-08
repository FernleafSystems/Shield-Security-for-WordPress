<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\BuildRuleFromForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\{
	ParseRuleBuilderForm,
	RuleFormBuilderVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\{
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
}
