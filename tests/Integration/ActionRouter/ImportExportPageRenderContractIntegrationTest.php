<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Enable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageImportExport;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as SitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ImportExportPageRenderContractIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireDb( SitesDB::DB_KEY );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_masterurl',
			'importexport_pending_network_invites',
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
		$this->assertFalse( (bool)$this->renderVars()[ 'network_setup' ][ 'has_master_url' ] );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$this->assertTrue( (bool)$this->renderFlags()[ 'has_master_url' ] );
		$this->assertTrue( (bool)$this->renderVars()[ 'network_setup' ][ 'has_master_url' ] );
		$this->assertSame( 'https://master.example.com', $this->renderVars()[ 'network_setup' ][ 'current_master_url' ] );
	}

	public function test_tabs_contract_uses_three_machine_tabs_and_file_default() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1', 'import_export_level_2' ] );

		$tabs = $this->renderVars()[ 'import_export_tabs' ];
		$this->assertSame( [ 'file', 'network_setup', 'sync_sites' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [ true, false, false ], \array_column( $tabs, 'is_active' ) );
		$this->assertSame( [ true, true, true ], \array_column( $tabs, 'is_available' ) );
		$this->assertSame( 'Network Setup', $tabs[ 1 ][ 'label' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();
		$this->assertStringContainsString( 'data-import-export-tab="file"', $html );
		$this->assertStringContainsString( 'data-import-export-tab="network_setup"', $html );
		$this->assertStringContainsString( 'data-import-export-tab="sync_sites"', $html );
		$this->assertStringContainsString( 'data-import-export-panel="file"', $html );
		$this->assertStringContainsString( 'data-import-export-panel="network_setup"', $html );
		$this->assertStringContainsString( 'data-import-export-panel="sync_sites"', $html );
		$this->assertStringContainsString( 'id="ImportExportFileForm"', $html );
		$this->assertStringContainsString( 'id="ImportFile"', $html );
		$this->assertStringContainsString( 'id="SubmitForm"', $html );
		$this->assertStringContainsString( 'id="ExportDownload"', $html );
	}

	public function test_sync_only_capability_makes_network_setup_the_active_tab() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );

		$tabs = $this->renderVars()[ 'import_export_tabs' ];

		$this->assertSame( [ false, true, false ], \array_column( $tabs, 'is_active' ) );
		$this->assertSame( [ false, true, true ], \array_column( $tabs, 'is_available' ) );
	}

	public function test_network_setup_contract_reflects_active_sync_site_rows() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_masterurl', '' )->store();
		$repo = new SiteRepository();
		$repo->upsertActive( 'https://child-one.example.com', SitesDB::SOURCE_MANUAL );
		$repo->upsertActive( 'https://child-two.example.com', SitesDB::SOURCE_MANUAL );

		$networkSetup = $this->renderVars()[ 'network_setup' ];

		$this->assertSame( 2, $networkSetup[ 'authorised_url_count' ] );
		$this->assertSame( [ false, false, true ], \array_column( $networkSetup[ 'status_cards' ], 'is_active' ) );
		$this->assertSame( 'Y', $networkSetup[ 'setup_form' ][ 'network_options' ][ 0 ][ 'value' ] );
		$this->assertSame( 'N', $networkSetup[ 'setup_form' ][ 'network_options' ][ 1 ][ 'value' ] );
		$this->assertSame( 'NC', $networkSetup[ 'setup_form' ][ 'network_options' ][ 2 ][ 'value' ] );
	}

	public function test_sync_sites_disabled_gate_replaces_table_for_licensed_disabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$data = $this->renderData();
		$pane = $data[ 'vars' ][ 'sync_sites' ][ 'disabled_pane' ];
		$action = $pane[ 'actions' ][ 0 ];

		$this->assertSame( ImportExportController::SYNC_STATE_DISABLED, $data[ 'flags' ][ 'sync_sites_state' ] );
		$this->assertSame( ImportExportController::SYNC_STATE_DISABLED, $data[ 'vars' ][ 'sync_sites' ][ 'sync_state' ] );
		$this->assertNotSame( '', $pane[ 'message' ] );
		$this->assertSame( PluginImportExport_Enable::SLUG, $action[ 'attributes' ][ 'data-ex' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();
		$this->assertStringContainsString( 'data-shield-scan-pane-disabled="1"', $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
	}

	public function test_sync_sites_table_renders_for_licensed_enabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();

		$this->assertSame( ImportExportController::SYNC_STATE_ENABLED, $this->renderFlags()[ 'sync_sites_state' ] );
		$this->assertTrue( (bool)$this->renderVars()[ 'sync_sites' ][ 'is_enabled' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringContainsString( 'ShieldTable-ImportExportSites', $html );
		$this->assertStringNotContainsString( 'data-ex="'.PluginImportExport_Enable::SLUG.'"', $html );
	}

	public function test_sync_sites_pro_gate_is_not_replaced_by_disabled_gate() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$this->assertSame( ImportExportController::SYNC_STATE_UNAVAILABLE, $this->renderFlags()[ 'sync_sites_state' ] );
		$this->assertTrue( (bool)$this->renderVars()[ 'sync_sites' ][ 'is_unavailable' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringNotContainsString( 'data-shield-scan-pane-disabled="1"', $html );
		$this->assertStringNotContainsString( 'data-ex="'.PluginImportExport_Enable::SLUG.'"', $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
	}

	public function test_network_invite_review_mode_renders_focused_review_surface() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$invite = ( new NetworkInviteRepository() )->receive( 'https://93.184.216.42/review-master' );
		$page = new PageImportExportContractProbe( [
			NetworkInviteRepository::REVIEW_QUERY_KEY => $invite[ 'id' ],
		] );

		$data = $page->renderDataForTest();
		$review = $data[ 'vars' ][ 'network_invite_review' ];

		$this->assertTrue( (bool)$data[ 'flags' ][ 'has_network_invite_review' ] );
		$this->assertSame( $invite[ 'id' ], $review[ 'invite' ][ 'id' ] );
		$this->assertArrayNotHasKey( 'actions', $review );

		$html = $page->renderOutputForTest();
		$this->assertStringContainsString( 'data-import-export-network-invite-review="1"', $html );
		$this->assertStringContainsString( 'ImportExportNetworkInviteAcceptForm', $html );
		$this->assertStringNotContainsString( 'data-import-export-tab="file"', $html );
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

	private function renderVars() :array {
		$data = $this->renderData();
		$this->assertIsArray( $data[ 'vars' ] );
		return $data[ 'vars' ];
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
