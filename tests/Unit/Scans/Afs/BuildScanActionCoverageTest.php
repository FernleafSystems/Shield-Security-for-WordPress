<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	BuildScanAction,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class BuildScanActionCoverageTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_coverage_family_contract_is_exact_and_ordered() :void {
		$this->assertSame( [
			'core_integrity',
			'plugin_integrity',
			'theme_integrity',
			'wproot_unidentified',
			'wpcontent_unidentified',
			'malware',
		], ScanActionVO::COVERAGE_FAMILIES );
	}

	/**
	 * @dataProvider provideScopeCoverage
	 * @param array<string,bool> $enabled
	 * @param list<string> $expected
	 */
	public function test_build_derives_ordered_coverage_from_scope_and_enabled_scanners(
		string $scopeType,
		array $enabled,
		array $expected
	) :void {
		$this->installController( $enabled );
		$action = new ScanActionVO();
		$action->scope_type = $scopeType;
		$action->created_at = 1;

		( new CoverageOnlyBuildScanAction() )
			->setScanActionVO( $action )
			->build();

		$this->assertSame( $expected, $action->coverage_families );
	}

	public function provideScopeCoverage() :array {
		$all = [
			'core'      => true,
			'plugin'    => true,
			'theme'     => true,
			'wproot'    => true,
			'wpcontent' => true,
			'malware'   => true,
		];

		return [
			'full' => [
				'full',
				$all,
				ScanActionVO::COVERAGE_FAMILIES,
			],
			'plugin' => [
				'plugin',
				$all,
				[
					ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_MALWARE,
				],
			],
			'theme' => [
				'theme',
				$all,
				[
					ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_MALWARE,
				],
			],
			'core' => [
				'core',
				$all,
				[
					ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
				],
			],
			'no enabled family' => [
				'full',
				\array_fill_keys( \array_keys( $all ), false ),
				[],
			],
			'disabled families omitted in stable order' => [
				'full',
				\array_merge( $all, [
					'plugin'  => false,
					'wproot'  => false,
					'malware' => false,
				] ),
				[
					ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_WPCONTENT_UNIDENTIFIED,
				],
			],
		];
	}

	/**
	 * @param array<string,bool> $enabled
	 */
	private function installController( array $enabled ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'configuration' => new class {
				public function def( string $key ) :array {
					unset( $key );
					return [ 'php' ];
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class( $enabled ) {
				private array $enabled;

				public function __construct( array $enabled ) {
					$this->enabled = $enabled;
				}

				public function AFS() :object {
					return new class( $this->enabled ) {
						private array $enabled;

						public function __construct( array $enabled ) {
							$this->enabled = $enabled;
						}

						public function isScanEnabledWpCore() :bool {
							return $this->enabled[ 'core' ];
						}

						public function isScanEnabledPlugins() :bool {
							return $this->enabled[ 'plugin' ];
						}

						public function isScanEnabledThemes() :bool {
							return $this->enabled[ 'theme' ];
						}

						public function isScanEnabledWpRoot() :bool {
							return $this->enabled[ 'wproot' ];
						}

						public function isScanEnabledWpContent() :bool {
							return $this->enabled[ 'wpcontent' ];
						}

						public function isEnabledMalwareScanPHP() :bool {
							return $this->enabled[ 'malware' ];
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class CoverageOnlyBuildScanAction extends BuildScanAction {

	protected function buildScanItems() {
	}
}
