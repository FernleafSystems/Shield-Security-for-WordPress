<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class OptionsFormForRenderDataTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_config_item_focus_marks_target_option_and_section() :void {
		$action = new OptionsFormFor( [
			'options'     => [
				'unit_security_admin_flag',
				'unit_headers_flag',
			],
			'config_item' => 'unit_headers_flag',
		] );

		$renderData = $this->invokeNonPublicMethod( $action, 'getRenderData' );
		$sections = $renderData[ 'vars' ][ 'all_options' ] ?? [];

		$this->assertCount( 2, $sections );
		$this->assertSame(
			[ 'section_security_headers' ],
			\array_values( \array_map(
				static fn( array $section ) :string => $section[ 'slug' ],
				\array_filter( $sections, static fn( array $section ) :bool => (bool)( $section[ 'is_focus' ] ?? false ) )
			) )
		);

		$sectionsBySlug = [];
		foreach ( $sections as $section ) {
			$sectionsBySlug[ $section[ 'slug' ] ] = $section;
		}

		$this->assertTrue( (bool)( $sectionsBySlug[ 'section_security_headers' ][ 'is_focus' ] ?? false ) );
		$this->assertFalse( (bool)( $sectionsBySlug[ 'section_security_admin_settings' ][ 'is_focus' ] ?? true ) );
		$this->assertTrue( $this->isFocusedOptionPresent( $sectionsBySlug[ 'section_security_headers' ][ 'options' ] ?? [], 'unit_headers_flag' ) );
		$this->assertFalse( $this->isFocusedOptionPresent( $sectionsBySlug[ 'section_security_admin_settings' ][ 'options' ] ?? [], 'unit_security_admin_flag' ) );
	}

	private function isFocusedOptionPresent( array $options, string $key ) :bool {
		foreach ( $options as $option ) {
			if ( ( $option[ 'key' ] ?? '' ) === $key ) {
				return (bool)( $option[ 'is_focus' ] ?? false );
			}
		}
		return false;
	}

	private function installControllerStub() :void {
		$optionDefinitions = [
			'unit_security_admin_flag' => [
				'key'          => 'unit_security_admin_flag',
				'section'      => 'section_security_admin_settings',
				'type'         => 'checkbox',
				'default'      => 'N',
				'name'         => 'Unit Security Admin Flag',
				'summary'      => 'Unit security admin summary',
				'description'  => [
					'Unit security admin description.',
				],
			],
			'unit_headers_flag' => [
				'key'          => 'unit_headers_flag',
				'section'      => 'section_security_headers',
				'type'         => 'checkbox',
				'default'      => 'N',
				'name'         => 'Unit Headers Flag',
				'summary'      => 'Unit headers summary',
				'description'  => [
					'Unit headers description.',
				],
			],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'configuration' => new class( $optionDefinitions ) {
				public array $options;
				public array $sections;

				public function __construct( array $options ) {
					$this->options = $options;
					$this->sections = [
						[ 'slug' => 'section_security_admin_settings' ],
						[ 'slug' => 'section_security_headers' ],
					];
				}

				public function optsForSection( string $section ) :array {
					return \array_values( \array_filter(
						$this->options,
						static fn( array $opt ) :bool => ( $opt[ 'section' ] ?? '' ) === $section
					) );
				}

				public function transferableOptions() :array {
					return [];
				}
			},
		];
		$controller->opts = new class( $optionDefinitions ) {
			private array $defs;
			private array $values;

			public function __construct( array $definitions ) {
				$this->defs = $definitions;
				$this->values = [
					'unit_security_admin_flag' => 'N',
					'unit_headers_flag'        => 'Y',
				];
			}

			public function optGet( string $key ) {
				return $this->values[ $key ] ?? null;
			}

			public function optHasAccess( string $key ) :bool {
				return isset( $this->defs[ $key ] );
			}

			public function optDef( string $key ) :array {
				return $this->defs[ $key ] ?? [];
			}
		};
		$controller->labels = new class {
			public bool $is_whitelabelled = false;
			public string $Name = 'Shield';

			public function getBrandName( string $brand ) :string {
				return $brand;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->caps = new class {
		};
		$controller->comps = (object)[
			'license'     => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
				}
			},
			'opts_lookup' => new class {
				public function getFirewallParametersWhitelist() :array {
					return [];
				}

				public function getXferExcluded() :array {
					return [];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
