<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Enable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageImportExport;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ImportExportPageRenderContractIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
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
		$this->assertFalse( (bool)$this->renderFlags()[ 'has_master_url' ] );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$this->assertTrue( (bool)$this->renderFlags()[ 'has_master_url' ] );
	}

	public function test_sync_sites_disabled_gate_replaces_table_for_licensed_disabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$data = $this->renderData();
		$pane = $data[ 'vars' ][ 'sync_sites_disabled_pane' ];
		$action = $pane[ 'actions' ][ 0 ];

		$this->assertSame( ImportExportController::SYNC_STATE_DISABLED, $data[ 'flags' ][ 'sync_sites_state' ] );
		$this->assertSame( 'Import and export is not enabled. Click to enable it.', $pane[ 'message' ] );
		$this->assertSame( PluginImportExport_Enable::SLUG, $action[ 'attributes' ][ 'data-ex' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();
		$this->assertStringContainsString( 'data-shield-scan-pane-disabled="1"', $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
	}

	public function test_sync_sites_table_renders_for_licensed_enabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();

		$this->assertSame( ImportExportController::SYNC_STATE_ENABLED, $this->renderFlags()[ 'sync_sites_state' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringContainsString( 'ShieldTable-ImportExportSites', $html );
		$this->assertStringNotContainsString( 'data-ex="'.PluginImportExport_Enable::SLUG.'"', $html );
	}

	public function test_sync_sites_pro_gate_is_not_replaced_by_disabled_gate() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$this->assertSame( ImportExportController::SYNC_STATE_UNAVAILABLE, $this->renderFlags()[ 'sync_sites_state' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringNotContainsString( 'data-shield-scan-pane-disabled="1"', $html );
		$this->assertStringNotContainsString( 'data-ex="'.PluginImportExport_Enable::SLUG.'"', $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
	}

	private function assertCapabilityFlags(
		array $capabilities,
		bool $canImportExport,
		bool $canImportExportFile,
		bool $canImportExportSync
	) :void {
		$this->enablePremiumCapabilities( $capabilities );
		$flags = $this->renderFlags();

		$this->assertSame( $canImportExport, (bool)$flags[ 'can_importexport' ] );
		$this->assertSame( $canImportExportFile, (bool)$flags[ 'can_importexport_file' ] );
		$this->assertSame( $canImportExportSync, (bool)$flags[ 'can_importexport_sync' ] );
	}

	private function renderFlags() :array {
		$data = $this->renderData();
		$this->assertIsArray( $data[ 'flags' ] );
		return $data[ 'flags' ];
	}

	private function renderData() :array {
		return ( new PageImportExportContractProbe() )->renderDataForTest();
	}
}

class PageImportExportContractProbe extends PageImportExport {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	public function renderOutputForTest() :string {
		return $this->buildRenderOutput( $this->buildRenderData() );
	}
}
