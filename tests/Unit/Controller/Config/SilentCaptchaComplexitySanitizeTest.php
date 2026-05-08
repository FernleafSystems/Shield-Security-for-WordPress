<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class SilentCaptchaComplexitySanitizeTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_presets_normalise_legacy_to_low_before_scope_validation() :void {
		$this->assertSame( SilentCaptchaComplexity::LOW, ( new PreSetOptSanitize( 'silentcaptcha_complexity', 'legacy' ) )->run() );
	}

	public function test_presets_normalise_unknown_value_to_medium_before_scope_validation() :void {
		$this->assertSame( SilentCaptchaComplexity::MEDIUM, ( new PreSetOptSanitize( 'silentcaptcha_complexity', 'unknown' ) )->run() );
	}

	public function test_plugin_spec_no_longer_exposes_legacy_complexity() :void {
		$spec = \json_decode(
			\file_get_contents( \dirname( __DIR__, 4 ).'/plugin-spec/34_options.json' ),
			true,
			512,
			\JSON_THROW_ON_ERROR
		);
		$option = null;
		foreach ( $spec as $candidate ) {
			if ( ( $candidate[ 'key' ] ?? '' ) === 'silentcaptcha_complexity' ) {
				$option = $candidate;
				break;
			}
		}

		$this->assertIsArray( $option );
		$this->assertNotContains( 'legacy', \array_column( $option[ 'value_options' ] ?? [], 'value_key' ) );
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options' => [
					'silentcaptcha_complexity' => true,
				],
			],
		];
		$controller->opts = new class {
			public function optType( string $key ) :string {
				return $key === 'silentcaptcha_complexity' ? 'select' : '';
			}

			public function optDef( string $key ) :array {
				return [
					'type'          => 'select',
					'value_options' => \array_map(
						static fn( string $value ) :array => [ 'value_key' => $value ],
						SilentCaptchaComplexity::VALID
					),
				];
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
