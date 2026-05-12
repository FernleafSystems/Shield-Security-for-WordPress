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
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Processors\ResponseProcessor,
	Responses\HookAddAction,
	Responses\HookAddFilter,
	Responses\SetRequestToBeLogged,
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestGeneral,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class ResponseProcessorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_ip'        => new IpUtils(),
			'service_request'   => $this->buildRequestService(),
			'service_wpgeneral' => $this->buildGeneralService(),
		] );

		Functions\when( '__' )->returnArg();
		Functions\when( '__return_false' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => \parse_url( $url, $component )
		);
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_legacy_hook_args_reach_add_filter_as_accepted_args() :void {
		Functions\expect( 'add_filter' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::on( static fn( $callback ) :bool => \is_callable( $callback ) ),
				1000,
				0
			)
			->andReturn( true );

		$rule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_hook_filter_rule',
			'immediate_exec_response' => true,
			'responses'               => [
				[
					'response' => HookAddFilter::class,
					'params'   => [
						'hook'     => 'test_filter_hook',
						'callback' => '__return_false',
						'priority' => '1000',
						'args'     => '0',
					],
				],
			],
		] );

		( new ResponseProcessor( $rule ) )
			->setThisRequest( new ThisRequest() )
			->run();

		$this->addToAssertionCount( 1 );
	}

	public function test_hook_add_action_consumes_accepted_args_as_hook_argument_count() :void {
		Functions\expect( 'add_action' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::on( static fn( $callback ) :bool => \is_callable( $callback ) ),
				25,
				2
			)
			->andReturn( true );

		( new HookAddAction() )
			->setThisRequest( new ThisRequest() )
			->setParams( [
				'hook'          => 'test_action_hook',
				'callback'      => static function () :void {
				},
				'priority'      => 25,
				'accepted_args' => 2,
			] )
			->execResponse();

		$this->addToAssertionCount( 1 );
	}

	public function test_legacy_log_request_hook_priority_reaches_add_filter_as_priority() :void {
		Functions\expect( 'add_filter' )
			->once()
			->with(
				'shield/is_log_traffic',
				'__return_true',
				25
			)
			->andReturn( true );

		$rule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_log_request_rule',
			'immediate_exec_response' => true,
			'responses'               => [
				[
					'response' => SetRequestToBeLogged::class,
					'params'   => [
						'do_log'        => true,
						'hook_priority' => '25',
					],
				],
			],
		] );

		( new ResponseProcessor( $rule ) )
			->setThisRequest( new ThisRequest() )
			->run();

		$this->addToAssertionCount( 1 );
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'shield',
				'slug_plugin' => 'security',
			],
		];
		$controller->comps = (object)[
			'events' => new class() {
				public function fireEvent( string $event ) :void {
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function buildRequestService() :UnitTestRequest {
		return new class() extends UnitTestRequest {
			public function __construct() {
				parent::__construct();
				$this->server = [];
			}
		};
	}

	private function buildGeneralService() :UnitTestGeneral {
		return new class() extends UnitTestGeneral {
			public function getLocale( $separator = '_' ) {
				unset( $separator );
				return 'en_US';
			}

			public function isAjax() :bool {
				return false;
			}

			public function isCron() :bool {
				return false;
			}

			public function isDebug() :bool {
				return false;
			}

			public function isWpCli() :bool {
				return false;
			}

			public function isXmlrpc() :bool {
				return false;
			}

			public function isPermalinksEnabled() :bool {
				return true;
			}
		};
	}
}
