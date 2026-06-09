<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_DisconnectMaster;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_SetEnabled;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport\FormAuthoriseUrls;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageImportExport;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as SitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
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

	public function test_master_url_value_reflects_network_sync_contract() :void {
		$con = $this->requireController();

		$con->opts->optSet( 'importexport_masterurl', '' )->store();
		$this->assertSame( '', $this->renderVars()[ 'network_sync' ][ 'current_master_url' ] );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$this->assertSame( 'https://master.example.com', $this->renderVars()[ 'network_sync' ][ 'current_master_url' ] );
	}

	public function test_tabs_contract_uses_two_machine_tabs_and_network_default() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1', 'import_export_level_2' ] );

		$tabs = $this->renderVars()[ 'import_export_tabs' ];
		$this->assertSame( [ 'network_sync', 'file' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [ true, false ], \array_column( $tabs, 'is_active' ) );
		$this->assertSame( [ true, true ], \array_column( $tabs, 'is_available' ) );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();
		$this->assertStringContainsString( 'data-import-export-tab="network_sync"', $html );
		$this->assertStringContainsString( 'data-import-export-tab="file"', $html );
		$this->assertStringContainsString( 'data-import-export-panel="network_sync"', $html );
		$this->assertStringContainsString( 'data-import-export-panel="file"', $html );
		$this->assertStringNotContainsString( 'data-import-export-tab="network_setup"', $html );
		$this->assertStringNotContainsString( 'data-import-export-tab="sync_sites"', $html );
		$this->assertStringNotContainsString( 'data-import-export-panel="network_setup"', $html );
		$this->assertStringNotContainsString( 'data-import-export-panel="sync_sites"', $html );
		$this->assertStringContainsString( 'id="ImportExportFileForm"', $html );
		$this->assertStringContainsString( 'id="ImportFile"', $html );
		$this->assertStringContainsString( 'id="SubmitForm"', $html );
		$this->assertStringContainsString( 'id="ExportDownload"', $html );
	}

	public function test_file_only_capability_makes_file_the_active_tab() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1' ] );

		$tabs = $this->renderVars()[ 'import_export_tabs' ];

		$this->assertSame( [ false, true ], \array_column( $tabs, 'is_active' ) );
		$this->assertSame( [ false, true ], \array_column( $tabs, 'is_available' ) );
	}

	public function test_network_sync_contract_exposes_task_and_connection_modes() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', '' )
			->store();

		$networkSync = $this->renderVars()[ 'network_sync' ];

		$this->assertSame( ImportExportController::SYNC_STATE_ENABLED, $networkSync[ 'sync_state' ] );
		$this->assertSame( [ 'connect', 'clients' ], \array_column( $networkSync[ 'tasks' ], 'key' ) );
		$this->assertSame( [ 'NC', 'Y' ], \array_column( $networkSync[ 'connect' ][ 'import_mode_options' ], 'value' ) );
		$this->assertSame( [ 'trusted', 'key' ], \array_column( $networkSync[ 'connect' ][ 'verification_options' ], 'value' ) );
	}

	public function test_disconnect_control_appears_only_when_master_url_exists() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();

		$con->opts->optSet( 'importexport_masterurl', '' )->store();
		$this->assertFalse( (bool)$this->renderVars()[ 'network_sync' ][ 'connect' ][ 'disconnect' ][ 'is_available' ] );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$disconnect = $this->renderVars()[ 'network_sync' ][ 'connect' ][ 'disconnect' ];

		$this->assertTrue( (bool)$disconnect[ 'is_available' ] );
	}

	public function test_disabled_sync_hides_workbench_and_table() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$data = $this->renderData();
		$networkSync = $data[ 'vars' ][ 'network_sync' ];

		$this->assertSame( ImportExportController::SYNC_STATE_DISABLED, $data[ 'flags' ][ 'network_sync_state' ] );
		$this->assertSame( ImportExportController::SYNC_STATE_DISABLED, $networkSync[ 'sync_state' ] );
		$this->assertFalse( (bool)$networkSync[ 'is_enabled' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();
		$this->assertStringContainsString( 'data-import-export-network-disabled="1"', $html );
		$this->assertStringNotContainsString( 'data-import-export-workbench="1"', $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
	}

	public function test_enabled_sync_renders_workbench_table_and_new_controls() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();

		$this->assertSame( ImportExportController::SYNC_STATE_ENABLED, $this->renderFlags()[ 'network_sync_state' ] );
		$this->assertTrue( (bool)$this->renderVars()[ 'network_sync' ][ 'is_enabled' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringContainsString( 'data-import-export-workbench="1"', $html );
		$this->assertStringContainsString( 'data-import-export-task="connect"', $html );
		$this->assertStringContainsString( 'data-import-export-task="clients"', $html );
		$this->assertStringContainsString( 'ShieldTable-ImportExportSites', $html );
		$this->assertStringContainsString( 'name="ShieldNetwork"', $html );
		$this->assertStringContainsString( 'value="NC"', $html );
		$this->assertStringContainsString( 'value="Y"', $html );
		$this->assertStringContainsString( 'data-import-export-auth-choice="trusted"', $html );
		$this->assertStringContainsString( 'data-import-export-auth-choice="key"', $html );
		$this->assertStringNotContainsString( 'id="ImportExportClientSecretKey"', $html );
	}

	public function test_sync_pro_gate_is_not_replaced_by_disabled_gate() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$this->assertSame( ImportExportController::SYNC_STATE_UNAVAILABLE, $this->renderFlags()[ 'network_sync_state' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringNotContainsString( 'data-import-export-network-disabled="1"', $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
	}

	public function test_authorise_client_sites_form_contains_readonly_secret_key() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );

		$html = self::con()->action_router->render( FormAuthoriseUrls::class );

		$this->assertStringContainsString( 'id="ImportExportClientSecretKey"', $html );
		$this->assertStringContainsString( 'readonly', $html );
		$this->assertStringContainsString( ( new ImportExportController() )->getImportExportSecretKey(), $html );
	}

	public function test_set_enabled_action_stores_disabled_state_and_clears_pending_invites() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$con->opts->optSet( 'importexport_enable', 'Y' )->store();
		$invite = ( new NetworkInviteRepository() )->receive( 'https://93.184.216.42/master' );
		$this->assertNotEmpty( $invite );

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_SetEnabled::SLUG, [
			'enabled' => 'N',
		] )->payload();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertTrue( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( 'N', (string)$con->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( [], $con->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
	}

	public function test_set_enabled_action_stores_enabled_state() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$con->opts->optSet( 'importexport_enable', 'N' )->store();

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_SetEnabled::SLUG, [
			'enabled' => 'Y',
		] )->payload();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertTrue( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'importexport_enable' ) );
	}

	public function test_set_enabled_action_rejects_sync_enable_without_sync_capability() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_1' ] );
		$con = $this->requireController();
		$con->opts->optSet( 'importexport_enable', 'N' )->store();

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_SetEnabled::SLUG, [
			'enabled' => 'Y',
		] )->payload();

		$this->assertFalse( (bool)$payload[ 'success' ] );
		$this->assertFalse( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( 'N', (string)$con->opts->optGet( 'importexport_enable' ) );
	}

	public function test_set_enabled_action_rejects_invalid_enabled_payload_without_mutation() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$con->opts->optSet( 'importexport_enable', 'Y' )->store();

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_SetEnabled::SLUG, [
			'enabled' => 'maybe',
		] )->payload();

		$this->assertFalse( (bool)$payload[ 'success' ] );
		$this->assertFalse( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'importexport_enable' ) );
	}

	public function test_disconnect_master_action_clears_master_url() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://master.example.com' )
			->store();

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_DisconnectMaster::SLUG )->payload();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertTrue( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( '', (string)$con->opts->optGet( 'importexport_masterurl' ) );
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
		$this->assertStringNotContainsString( 'data-import-export-tab="network_sync"', $html );
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
