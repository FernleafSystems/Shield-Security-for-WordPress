<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Config;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PreSetOptSanitizeMultipleSelectTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	/**
	 * @dataProvider validValueProvider
	 */
	public function test_valid_string_members_are_preserved( array $value ) :void {
		$this->assertSame( $value, ( new PreSetOptSanitize( 'file_locker', $value ) )->run() );
	}

	public static function validValueProvider() :array {
		return [
			'empty'       => [ [] ],
			'sequential'  => [ [ 'wpconfig', 'root_index' ] ],
			'duplicates'  => [ [ 'wpconfig', 'root_index', 'wpconfig' ] ],
			'associative' => [ [ 'first' => 'root_index', 'second' => 'wpconfig' ] ],
		];
	}

	/**
	 * @dataProvider invalidValueProvider
	 */
	public function test_invalid_member_is_rejected_with_controlled_exception( $invalid ) :void {
		$this->expectException( \Exception::class );
		( new PreSetOptSanitize( 'file_locker', [ 'wpconfig', $invalid, 'root_index' ] ) )->run();
	}

	public static function invalidValueProvider() :array {
		return [
			'integer' => [ 1 ],
			'float' => [ 1.5 ],
			'boolean' => [ true ],
			'array' => [ [ 'root_index' ] ],
			'object' => [ new \stdClass() ],
			'stringable object' => [ new PreSetMultipleSelectStringable() ],
			'null' => [ null ],
			'empty string' => [ '' ],
			'unknown string' => [ 'unknown_file' ],
		];
	}

	/**
	 * @dataProvider invalidOuterValueProvider
	 */
	public function test_invalid_outer_value_is_rejected_with_controlled_exception( $value ) :void {
		$this->expectException( \Exception::class );
		( new PreSetOptSanitize( 'file_locker', $value ) )->run();
	}

	public static function invalidOuterValueProvider() :array {
		return [
			'null'    => [ null ],
			'string'  => [ 'wpconfig' ],
			'integer' => [ 1 ],
			'float'   => [ 1.5 ],
			'boolean' => [ true ],
			'object'  => [ new \stdClass() ],
		];
	}

	public function test_resource_member_is_rejected_with_controlled_exception() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->expectException( \Exception::class );
			( new PreSetOptSanitize( 'file_locker', [ 'wpconfig', $resource ] ) )->run();
		}
		finally {
			\fclose( $resource );
		}
	}

	public function test_resource_outer_value_is_rejected_with_controlled_exception() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->expectException( \Exception::class );
			( new PreSetOptSanitize( 'file_locker', $resource ) )->run();
		}
		finally {
			\fclose( $resource );
		}
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options' => [ 'file_locker' => true ],
			],
		];
		$controller->opts = new class {
			public function optType( string $key ) :string {
				return $key === 'file_locker' ? 'multiple_select' : '';
			}

			public function optDef( string $key ) :array {
				return [
					'type' => 'multiple_select',
					'value_options' => [
						[ 'value_key' => 'wpconfig' ],
						[ 'value_key' => 'root_index' ],
					],
				];
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}

class PreSetMultipleSelectStringable {

	public function __toString() :string {
		return 'wpconfig';
	}
}
