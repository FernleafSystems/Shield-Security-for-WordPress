<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin\PluginAdminPageHandler;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_DisconnectMaster;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_SetEnabled;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport\FormAuthoriseUrls;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageImportExport;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Handler as ProfilesDB;
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
		$this->requireDb( ProfilesDB::DB_KEY );
		$this->requireDb( SitesDB::DB_KEY );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_masterurl',
			'importexport_pending_network_invites',
			'importexport_network_invite_block_until',
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
		$this->assertSame( '', $this->renderVars()[ 'network_sync' ][ 'connect' ][ 'form' ][ 'master_site_url_value' ] );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$connected = $this->renderVars()[ 'network_sync' ][ 'connect' ][ 'connected' ];
		$this->assertSame( 'master.example.com', $connected[ 'master_host' ] );
		$this->assertArrayNotHasKey( 'master_url', $connected );
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
		$this->assertSame( [ 'connect', 'clients', 'profile' ], \array_column( $networkSync[ 'tasks' ], 'key' ) );
		$connect = $networkSync[ 'connect' ];
		$this->assertFalse( (bool)$connect[ 'is_connected' ] );
		$this->assertArrayHasKey( 'form', $connect );
		$this->assertArrayHasKey( 'standalone', $connect );
		$this->assertArrayNotHasKey( 'connected', $connect );
		$this->assertArrayNotHasKey( 'sync_now', $connect );
		$this->assertArrayHasKey( 'rail', $networkSync );
		$this->assertSame( 'ImportSiteFormPanel', $connect[ 'form' ][ 'panel_id' ] );
		$this->assertSame( 'ImportSiteFormReveal', $connect[ 'form' ][ 'reveal_id' ] );
		$this->assertSame( [ 'NC', 'Y' ], \array_column( $connect[ 'form' ][ 'import_mode_options' ], 'value' ) );
		foreach ( $connect[ 'form' ][ 'import_mode_options' ] as $option ) {
			$this->assertArrayHasKey( 'action_label', $option );
			$this->assertNotSame( '', $option[ 'action_label' ] );
		}
		$this->assertSame( [ 'trusted', 'key' ], \array_column( $connect[ 'form' ][ 'verification_options' ], 'value' ) );
	}

	public function test_profile_copy_from_master_contract_is_rendered_for_enabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts
			->optSet( 'importexport_enable', 'Y' )
			->store();

		$profile = $this->renderVars()[ 'network_sync' ][ 'profile' ];
		$this->assertArrayHasKey( 'copy_from_master', $profile );
		$this->assertSame( 'ImportExportProfileCopyFromMaster', $profile[ 'copy_from_master' ][ 'id' ] );
		$this->assertArrayHasKey( 'confirm_message', $profile[ 'copy_from_master' ] );
		$this->assertArrayHasKey( 'confirm_label', $profile[ 'copy_from_master' ] );
		$this->assertNotSame( '', $profile[ 'copy_from_master' ][ 'confirm_message' ] );
		$this->assertNotSame( '', $profile[ 'copy_from_master' ][ 'confirm_label' ] );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();
		$this->assertStringContainsString( 'id="ImportExportProfileCopyFromMaster"', $html );
		$this->assertStringContainsString( 'data-import-export-profile-copy-from-master="1"', $html );
		$this->assertStringContainsString( 'data-confirm-label=', $html );
	}

	public function test_disconnect_control_appears_only_when_master_url_exists() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();

		$con->opts->optSet( 'importexport_masterurl', '' )->store();
		$connect = $this->renderVars()[ 'network_sync' ][ 'connect' ];
		$this->assertFalse( (bool)$connect[ 'is_connected' ] );
		$this->assertArrayNotHasKey( 'disconnect', $connect );

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();
		$connect = $this->renderVars()[ 'network_sync' ][ 'connect' ];

		$this->assertTrue( (bool)$connect[ 'is_connected' ] );
		$this->assertArrayHasKey( 'label', $connect[ 'disconnect' ] );
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

	public function test_enabled_sync_without_connected_clients_hides_client_table() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', '' )
			->store();

		$this->assertSame( ImportExportController::SYNC_STATE_ENABLED, $this->renderFlags()[ 'network_sync_state' ] );
		$networkSync = $this->renderVars()[ 'network_sync' ];
		$this->assertTrue( (bool)$networkSync[ 'is_enabled' ] );
		$this->assertSame( 0, $networkSync[ 'clients' ][ 'active_count' ] );
		$this->assertFalse( (bool)$networkSync[ 'clients' ][ 'has_connected_sites' ] );
		$this->assertSame(
			'No client sites are connected. Click Add client sites to connect sites.',
			$networkSync[ 'clients' ][ 'empty_message' ]
		);

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringContainsString( 'data-import-export-workbench="1"', $html );
		$this->assertStringContainsString( 'data-import-export-task="connect"', $html );
		$this->assertStringContainsString( 'data-import-export-task="clients"', $html );
		$this->assertStringContainsString( 'data-import-export-task="profile"', $html );
		$this->assertStringContainsString( 'data-import-export-standalone-site="1"', $html );
		$this->assertStringContainsString( 'data-import-export-connect-reveal="1"', $html );
		$this->assertStringContainsString( 'data-import-export-connect-form-panel="1"', $html );
		$this->assertMatchesRegularExpression(
			'#<div[^>]+data-import-export-connect-form-panel="1"[^>]+hidden#',
			$html
		);
		$this->assertStringContainsString( $networkSync[ 'clients' ][ 'empty_message' ], $html );
		$this->assertStringNotContainsString( 'ShieldTable-ImportExportSites', $html );
		$this->assertStringContainsString( 'name="ShieldNetwork"', $html );
		$this->assertStringContainsString( 'value="NC"', $html );
		$this->assertStringContainsString( 'value="Y"', $html );
		$this->assertStringContainsString( 'data-import-export-auth-choice="trusted"', $html );
		$this->assertStringContainsString( 'data-import-export-auth-choice="key"', $html );
		$this->assertStringNotContainsString( 'id="ImportExportClientSecretKey"', $html );
	}

	public function test_enabled_sync_with_connected_clients_renders_client_table() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', '' )
			->store();
		( new SiteRepository() )->upsertActive( 'https://connected-client.example.com', SitesDB::SOURCE_MANUAL );

		$networkSync = $this->renderVars()[ 'network_sync' ];

		$this->assertSame( 1, $networkSync[ 'clients' ][ 'active_count' ] );
		$this->assertTrue( (bool)$networkSync[ 'clients' ][ 'has_connected_sites' ] );
		$this->assertStringContainsString(
			'ShieldTable-ImportExportSites',
			( new PageImportExportContractProbe() )->renderOutputForTest()
		);
	}

	public function test_connected_sync_renders_master_host_without_connect_form() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://master.example.com/import' )
			->store();

		$connect = $this->renderVars()[ 'network_sync' ][ 'connect' ];
		$this->assertTrue( (bool)$connect[ 'is_connected' ] );
		$this->assertSame( 'master.example.com', $connect[ 'connected' ][ 'master_host' ] );
		$this->assertSame( 'ImportExportSyncNow', $connect[ 'sync_now' ][ 'id' ] );
		$this->assertSame( 'Sync settings now', $connect[ 'sync_now' ][ 'label' ] );
		$this->assertArrayNotHasKey( 'master_url', $connect[ 'connected' ] );
		$this->assertArrayNotHasKey( 'summary', $connect );
		$this->assertArrayNotHasKey( 'summary', $connect[ 'connected' ] );
		$this->assertArrayNotHasKey( 'form', $connect );

		$html = ( new PageImportExportContractProbe() )->renderOutputForTest();

		$this->assertStringContainsString( 'data-import-export-connected-master="1"', $html );
		$this->assertStringContainsString( 'data-import-export-disconnect="1"', $html );
		$this->assertStringContainsString( 'data-import-export-sync-now="1"', $html );
		$this->assertStringContainsString( 'id="ImportExportSyncNow"', $html );
		$this->assertStringContainsString( 'Sync settings now', $html );
		$this->assertStringContainsString( 'master.example.com', $html );
		$this->assertStringNotContainsString( 'https://master.example.com/import', $html );
		$this->assertStringNotContainsString( 'Current master connection', $html );
		$this->assertStringNotContainsString( 'id="ImportSiteForm"', $html );

		$masterPosition = \strpos( $html, 'master.example.com' );
		$syncPosition = \strpos( $html, 'data-import-export-sync-now="1"' );
		$this->assertIsInt( $masterPosition );
		$this->assertIsInt( $syncPosition );
		$this->assertLessThan( $syncPosition, $masterPosition );
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
		$this->assertNotNull( $review );
		$this->assertSame( $invite[ 'id' ], $review[ 'invite' ][ 'id' ] );
		$this->assertArrayNotHasKey( 'actions', $review );

		$html = $page->renderOutputForTest();
		$this->assertStringContainsString( 'data-import-export-network-invite-review="1"', $html );
		$this->assertStringContainsString( 'ImportExportNetworkInviteAcceptForm', $html );
		$this->assertStringContainsString( 'ImportExportNetworkInviteRejectForm', $html );
		$rejectPosition = \strpos( $html, 'id="ImportExportNetworkInviteRejectForm"' );
		$acceptPosition = \strpos( $html, 'form="ImportExportNetworkInviteAcceptForm"' );
		$this->assertIsInt( $rejectPosition );
		$this->assertIsInt( $acceptPosition );
		$this->assertLessThan( $acceptPosition, $rejectPosition );
		$this->assertStringNotContainsString( 'data-import-export-tab="file"', $html );
		$this->assertStringNotContainsString( 'data-import-export-tab="network_sync"', $html );
	}

	public function test_network_invite_review_mode_renders_from_plugin_admin_page_callback() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$invite = ( new NetworkInviteRepository() )->receive( 'https://93.184.216.42/callback-review-master' );

		$html = $this->capturePluginAdminPageCallbackOutput( [
			Constants::NAV_ID                        => PluginNavs::NAV_TOOLS,
			Constants::NAV_SUB_ID                    => PluginNavs::SUBNAV_TOOLS_IMPORT,
			NetworkInviteRepository::REVIEW_QUERY_KEY => $invite[ 'id' ],
			'unrelated'                             => 'drop-me',
		] );

		$this->assertStringContainsString( 'data-import-export-network-invite-review="1"', $html );
		$this->assertStringNotContainsString( 'data-import-export-tab="file"', $html );
		$this->assertStringNotContainsString( 'data-import-export-tab="network_sync"', $html );
	}

	public function test_network_invite_review_mode_hides_stale_invite_when_master_url_exists() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://master.example.com' )
			->store();
		$invite = $this->seedPendingInvite( 'https://93.184.216.42/stale-review-master' );
		$page = new PageImportExportContractProbe( [
			NetworkInviteRepository::REVIEW_QUERY_KEY => $invite[ 'id' ],
		] );

		$data = $page->renderDataForTest();

		$this->assertFalse( (bool)$data[ 'flags' ][ 'has_network_invite_review' ] );
		$this->assertNull( $data[ 'vars' ][ 'network_invite_review' ] );
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

	private function seedPendingInvite( string $masterUrl ) :array {
		$id = \hash( 'sha256', $masterUrl );
		$invite = [
			'id'         => $id,
			'master_url' => $masterUrl,
			'created_at' => 1712620800,
			'updated_at' => 1712620800,
		];
		$this->requireController()->opts->optSet( NetworkInviteRepository::OPTION_KEY, [
			$id => $invite,
		] )->store();
		return $invite;
	}

	private function capturePluginAdminPageCallbackOutput( array $actionData ) :string {
		$con = $this->requireController();
		$filter = $con->prefix( 'bypass_is_plugin_admin' );
		$isSecurityAdminSnapshot = $con->this_req->is_security_admin;
		$wpIsAjaxSnapshot = $con->this_req->wp_is_ajax;
		$level = \ob_get_level();
		\add_filter( $filter, '__return_true', 1000 );
		$con->this_req->is_security_admin = true;
		$con->this_req->wp_is_ajax = false;
		\ob_start();
		try {
			( new PluginAdminPageHandler( $actionData ) )->displayModuleAdminPage();
			return (string)\ob_get_clean();
		}
		finally {
			while ( \ob_get_level() > $level ) {
				\ob_end_clean();
			}
			$con->this_req->is_security_admin = $isSecurityAdminSnapshot;
			$con->this_req->wp_is_ajax = $wpIsAjaxSnapshot;
			\remove_filter( $filter, '__return_true', 1000 );
		}
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
