<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageImportExport;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ImportExportPageRenderContractIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_masterurl',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_render_flags_reflect_file_and_sync_capabilities() :void {
		$this->assertCapabilityFlags( [], false, false, false );
		$this->assertCapabilityFlags( [ 'import_export_level_1' ], true, true, false );
		$this->assertCapabilityFlags( [ 'import_export_level_2' ], true, false, true );
		$this->assertCapabilityFlags( [ 'import_export_level_1', 'import_export_level_2' ], true, true, true );
	}

	public function test_master_url_flag_reflects_configured_state() :void {
		$con = $this->requireController();

		$con->opts->optSet( 'importexport_masterurl', '' )->store();
		$this->assertFalse( (bool)( $this->renderFlags()[ 'has_master_url' ] ?? true ) );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$this->assertTrue( (bool)( $this->renderFlags()[ 'has_master_url' ] ?? false ) );
	}

	private function assertCapabilityFlags(
		array $capabilities,
		bool $canImportExport,
		bool $canImportExportFile,
		bool $canImportExportSync
	) :void {
		$this->enablePremiumCapabilities( $capabilities );
		$flags = $this->renderFlags();

		$this->assertSame( $canImportExport, (bool)( $flags[ 'can_importexport' ] ?? null ) );
		$this->assertSame( $canImportExportFile, (bool)( $flags[ 'can_importexport_file' ] ?? null ) );
		$this->assertSame( $canImportExportSync, (bool)( $flags[ 'can_importexport_sync' ] ?? null ) );
	}

	private function renderFlags() :array {
		$data = ( new PageImportExportContractProbe() )->renderDataForTest();
		$this->assertIsArray( $data[ 'flags' ] ?? null );
		return $data[ 'flags' ];
	}
}

class PageImportExportContractProbe extends PageImportExport {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}
