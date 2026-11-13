<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * File scan areas are a setting on every plan: WordPress core files can be chosen on Free, and the other
 * areas need Pro.
 */
class FileScanAreasPlanIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'file_scan_areas', 'enable_core_file_integrity_scan' ] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->disablePremiumCapabilities();
		parent::tear_down();
	}

	public function test_free_plan_can_choose_wordpress_core_files_only() :void {
		$this->disablePremiumCapabilities();
		$this->requireController()->opts->optSet( 'file_scan_areas', [ 'wp' ] )->store();

		$option = $this->displayedAreas();
		$this->assertFalse( $option[ 'disabled' ] );
		$this->assertTrue( $option[ 'value_options' ][ 'wp' ][ 'is_available' ] );
		foreach ( [ 'malware_php', 'plugins', 'themes', 'wpcontent', 'wproot' ] as $proArea ) {
			$this->assertFalse( $option[ 'value_options' ][ $proArea ][ 'is_available' ], $proArea );
		}
		$this->assertSame( [ 'wp' ], \array_values( $option[ 'value' ] ) );
	}

	public function test_free_plan_scans_only_the_areas_it_includes() :void {
		$this->disablePremiumCapabilities();
		$this->requireController()->opts->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'wproot' ] )->store();

		$this->assertSame( [ 'wp' ], $this->requireController()->comps->scans->AFS()->getFileScanAreas() );
	}

	public function test_pro_plan_can_choose_every_area() :void {
		$this->enablePremiumCapabilities( [ 'scan_malware_local', 'scan_pluginsthemes_local', 'scan_files_everywhere' ] );
		$this->requireController()->opts->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'wproot' ] )->store();

		$option = $this->displayedAreas();
		foreach ( $option[ 'value_options' ] as $area => $choice ) {
			$this->assertTrue( $choice[ 'is_available' ], $area );
		}
		$this->assertSame( [ 'wp', 'plugins', 'wproot' ], $this->requireController()->comps->scans->AFS()->getFileScanAreas() );
	}

	private function displayedAreas() :array {
		foreach ( ( new BuildOptionsForDisplay( [ 'file_scan_areas' ] ) )->standard() as $section ) {
			foreach ( $section[ 'options' ] as $option ) {
				if ( $option[ 'key' ] === 'file_scan_areas' ) {
					return $option;
				}
			}
		}
		$this->fail( 'File scan areas were not displayed.' );
	}
}
