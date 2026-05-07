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
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ResponseProcessorTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( '__return_false' )->justReturn( false );
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
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
}
