<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ImportExportProfileCopyFromMaster,
	ImportExportProfileOptionIncludeToggle,
	ImportExportProfileOptionsIncludeToggle,
	ImportExportProfileOptionsSave
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport\ProfileOptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Handler as ProfilesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\{
	NetworkInviteRepository,
	Profiles\ProfileOptionsCatalog,
	Profiles\ProfileRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportProfileActionsIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireDb( ProfilesDB::DB_KEY );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'admin_access_timeout',
			'bot_protection_locations',
			'display_plugin_badge',
			'enable_tracking',
			'import_id',
			'import_url_ids',
			'importexport_enable',
			'importexport_handshake_expires_at',
			'importexport_masterurl',
			'importexport_network_invite_block_until',
			'importexport_pending_network_invites',
			'importexport_secretkey',
			'importexport_secretkey_expires_at',
			'importexport_sites_migrated_at',
			'page_params_whitelist',
			'visitor_address_source',
			'xfer_excluded',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_profile_options_form_renders_profile_action_contract() :void {
		$payload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ProfileOptionsForm::SLUG );

		$this->assertArrayHasKey( 'render_output', $payload );
		$this->assertArrayHasKey( 'render_data', $payload );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'render_error', $payload );
		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertFalse( (bool)$payload[ 'render_error' ] );

		$this->assertIsString( $payload[ 'render_output' ] );
		$this->assertNotSame( '', \trim( $payload[ 'render_output' ] ) );
		$renderData = $payload[ 'render_data' ];
		$this->assertIsArray( $renderData );
		$this->assertArrayHasKey( 'flags', $renderData );
		$this->assertIsArray( $renderData[ 'flags' ] );
		$this->assertArrayHasKey( 'vars', $renderData );
		$this->assertIsArray( $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'profile_available', $renderData[ 'flags' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'profile_available' ] );
		$this->assertArrayHasKey( 'form_context', $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'options_save_action', $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'transfer_action', $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'transfer_group_action', $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'all_opts_keys', $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'groups', $renderData[ 'vars' ] );
		$this->assertSame( 'import_export_profile', $renderData[ 'vars' ][ 'form_context' ] );
		$this->assertSame( 'profile_form_save', $renderData[ 'vars' ][ 'options_save_action' ] );
		$this->assertSame( 'profile_xfer_include_toggle', $renderData[ 'vars' ][ 'transfer_action' ] );
		$this->assertSame( 'profile_xfer_group_include_toggle', $renderData[ 'vars' ][ 'transfer_group_action' ] );
		$this->assertEqualsCanonicalizing( ( new ProfileOptionsCatalog() )->profileableKeys(), $renderData[ 'vars' ][ 'all_opts_keys' ] );
		$this->assertIsArray( $renderData[ 'vars' ][ 'groups' ] );
		$this->assertProfileRenderGroupsCoverProfileableKeysOnce( $renderData[ 'vars' ][ 'groups' ] );
	}

	public function test_default_profile_is_created_with_default_flag_and_default_slug() :void {
		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();

		$this->assertNotEmpty( $profile );
		$this->assertSame( ProfileRepository::DEFAULT_SLUG, $profile->slug );
		$this->assertSame( ProfileRepository::DEFAULT_LABEL, $profile->label );
		$this->assertTrue( $profile->is_default );
	}

	public function test_default_profile_flag_is_normalised_to_single_profile() :void {
		$repo = new ProfileRepository();
		$default = $repo->ensureDefaultProfile();
		$this->assertNotEmpty( $default );

		$dbh = $this->requireController()->db_con->import_export_profiles;
		$now = \time();
		$record = $dbh->getRecord();
		$record->slug = 'temporary-default';
		$record->label = 'Temporary Default';
		$record->is_default = true;
		$record->config = \wp_json_encode( $repo->emptyConfig() );
		$record->created_at = $now;
		$record->updated_at = $now;
		$dbh->getQueryInserter()->insert( $record );
		$extraDefault = $repo->findBySlug( 'temporary-default' );
		$this->assertNotEmpty( $extraDefault );
		$this->assertTrue( $extraDefault->is_default );

		$repo->ensureDefaultProfile();

		$this->assertTrue( $repo->findById( $default->id )->is_default );
		$this->assertFalse( $repo->findById( $extraDefault->id )->is_default );
	}

	public function test_non_canonical_default_flag_is_not_adopted_as_default_profile() :void {
		$repo = new ProfileRepository();
		$dbh = $this->requireController()->db_con->import_export_profiles;
		$now = \time();
		$record = $dbh->getRecord();
		$record->slug = 'temporary-default';
		$record->label = 'Temporary Default';
		$record->is_default = true;
		$record->config = \wp_json_encode( $repo->emptyConfig() );
		$record->created_at = $now;
		$record->updated_at = $now;
		$dbh->getQueryInserter()->insert( $record );

		$profile = $repo->ensureDefaultProfile();

		$this->assertNotEmpty( $profile );
		$this->assertSame( ProfileRepository::DEFAULT_SLUG, $profile->slug );
		$this->assertTrue( $profile->is_default );
		$this->assertFalse( $repo->findBySlug( 'temporary-default' )->is_default );
	}

	public function test_profiles_table_recreates_after_warm_ready_cache_table_loss() :void {
		$con = $this->requireController();
		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );

		$schema = $con->db_con->import_export_profiles->getTableSchema();
		ProfilesDB::GetTableReadyCache()->setReady( $schema );
		$this->dropImportExportProfilesTable( false );

		try {
			$cachedHandler = $this->newImportExportProfilesHandler( true );
			$cachedHandler->execute();

			$this->assertTrue( $cachedHandler->isReady() );
			$this->assertTrue( Services::WpDb()->tableExists( $cachedHandler->getTable() ) );
			$this->assertTrue( ProfilesDB::GetTableReadyCache()->isReady( $cachedHandler->getTableSchema() ) );

			$con->db_con->reset();
			$profile = ( new ProfileRepository() )->ensureDefaultProfile();

			$this->assertNotEmpty( $profile );
			$this->assertSame( ProfileRepository::DEFAULT_SLUG, $profile->slug );
			$this->assertTrue( $profile->is_default );
		}
		finally {
			if ( !Services::WpDb()->tableExists( $schema->table ) ) {
				$repairHandler = $this->newImportExportProfilesHandler( false );
				$repairHandler->execute();
			}
			ProfilesDB::GetTableReadyCache()->setReady( $schema, false );
			Services::WpDb()->clearResultShowTables();
			$con->db_con->reset();
		}
	}

	public function test_profile_save_and_include_toggle_update_default_profile_only() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'display_plugin_badge', 'light' )
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->optSet( 'enable_tracking', 'Y' )
			->store();

		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );

		$savePayload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionsSave::SLUG, [
			'form_enc'    => [ FormParams::ENC_BASE64, FormParams::ENC_JSON ],
			'form_params' => \base64_encode( \wp_json_encode( [
				'all_opts_keys'          => 'display_plugin_badge,visitor_address_source,enable_tracking',
				'display_plugin_badge'   => 'disabled',
				'visitor_address_source' => 'AUTO_DETECT_IP',
				'enable_tracking'        => 'N',
			] ) ),
		] );
		$this->assertTrue( (bool)( $savePayload[ 'success' ] ?? false ) );
		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$config = $repo->configForProfile( $profile );
		$this->assertSame( 'disabled', $config[ 'options' ][ 'display_plugin_badge' ] );
		$this->assertSame( 'AUTO_DETECT_IP', $config[ 'options' ][ 'visitor_address_source' ] );
		$this->assertSame( 'N', $config[ 'options' ][ 'enable_tracking' ] );
		$this->assertSame( 'light', $con->opts->optGet( 'display_plugin_badge' ) );
		$this->assertSame( 'REMOTE_ADDR', $con->opts->optGet( 'visitor_address_source' ) );
		$this->assertSame( 'Y', $con->opts->optGet( 'enable_tracking' ) );

		$excludePayload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionIncludeToggle::SLUG, [
			'key'    => 'enable_tracking',
			'status' => 'exclude',
		] );
		$this->assertTrue( (bool)( $excludePayload[ 'success' ] ?? false ) );
		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$this->assertContains( 'enable_tracking', $repo->configForProfile( $profile )[ 'excluded' ] );

		$includePayload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionIncludeToggle::SLUG, [
			'key'    => 'enable_tracking',
			'status' => 'include',
		] );
		$this->assertTrue( (bool)( $includePayload[ 'success' ] ?? false ) );
		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$this->assertNotContains( 'enable_tracking', $repo->configForProfile( $profile )[ 'excluded' ] );

		$groupExcludePayload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionsIncludeToggle::SLUG, [
			'keys'   => 'display_plugin_badge,enable_tracking,importexport_enable,not_a_real_option',
			'status' => 'exclude',
		] );
		$this->assertTrue( (bool)( $groupExcludePayload[ 'success' ] ?? false ) );
		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$config = $repo->configForProfile( $profile );
		$this->assertContains( 'display_plugin_badge', $config[ 'excluded' ] );
		$this->assertContains( 'enable_tracking', $config[ 'excluded' ] );
		$this->assertNotContains( 'importexport_enable', $config[ 'excluded' ] );
		$this->assertNotContains( 'not_a_real_option', $config[ 'excluded' ] );

		$groupIncludePayload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionsIncludeToggle::SLUG, [
			'keys'   => 'display_plugin_badge,enable_tracking',
			'status' => 'include',
		] );
		$this->assertTrue( (bool)( $groupIncludePayload[ 'success' ] ?? false ) );
		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$config = $repo->configForProfile( $profile );
		$this->assertNotContains( 'display_plugin_badge', $config[ 'excluded' ] );
		$this->assertNotContains( 'enable_tracking', $config[ 'excluded' ] );
	}

	public function test_copy_from_master_updates_profile_values_and_preserves_profile_exclusions() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'display_plugin_badge', 'light' )
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->optSet( 'enable_tracking', 'Y' )
			->optSet( 'xfer_excluded', [] )
			->store();

		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );
		$this->assertTrue( $repo->saveOptionValues( $profile, [
			'display_plugin_badge'   => 'disabled',
			'visitor_address_source' => 'AUTO_DETECT_IP',
			'enable_tracking'        => 'N',
		] ) );
		$this->assertTrue( $repo->setOptionIncluded( $profile, 'enable_tracking', false ) );

		$con->opts
			->optSet( 'display_plugin_badge', 'dark' )
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->optSet( 'enable_tracking', 'Y' )
			->optSet( 'xfer_excluded', [ 'display_plugin_badge' ] )
			->store();

		$payload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileCopyFromMaster::SLUG );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'page_reload' ] ?? false ) );
		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$config = $repo->configForProfile( $profile );
		$this->assertSame( 'dark', $config[ 'options' ][ 'display_plugin_badge' ] );
		$this->assertSame( 'REMOTE_ADDR', $config[ 'options' ][ 'visitor_address_source' ] );
		$this->assertSame( 'Y', $config[ 'options' ][ 'enable_tracking' ] );
		$this->assertSame( [ 'enable_tracking' ], $config[ 'excluded' ] );
	}

	public function test_profile_save_resolves_supported_option_types_without_mutating_live_options() :void {
		$con = $this->requireController();
		$profileKeys = [
			'admin_access_timeout',
			'bot_protection_locations',
			'display_plugin_badge',
			'enable_tracking',
			'page_params_whitelist',
		];
		$this->assertSame(
			[],
			\array_values( \array_diff( $profileKeys, ( new ProfileOptionsCatalog() )->profileableKeys() ) )
		);

		$con->opts
			->optSet( 'admin_access_timeout', 30 )
			->optSet( 'bot_protection_locations', [ 'password' ] )
			->optSet( 'display_plugin_badge', 'light' )
			->optSet( 'enable_tracking', 'Y' )
			->optSet( 'page_params_whitelist', [ 'admin.php,existing_param' ] )
			->store();

		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );

		$payload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionsSave::SLUG, [
			'form_enc'    => [ FormParams::ENC_BASE64, FormParams::ENC_JSON ],
			'form_params' => \base64_encode( \wp_json_encode( [
				'all_opts_keys'            => \implode( ',', $profileKeys ),
				'admin_access_timeout'     => '45',
				'bot_protection_locations' => [ 'login', 'register' ],
				'display_plugin_badge'     => 'dark',
				'page_params_whitelist'    => "alpha,beta\n\n gamma,delta \n",
			] ) ),
		] );
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );

		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$config = $repo->configForProfile( $profile );
		$this->assertSame( 45, $config[ 'options' ][ 'admin_access_timeout' ] );
		$this->assertSame( [ 'login', 'register' ], $config[ 'options' ][ 'bot_protection_locations' ] );
		$this->assertSame( 'dark', $config[ 'options' ][ 'display_plugin_badge' ] );
		$this->assertSame( 'N', $config[ 'options' ][ 'enable_tracking' ] );
		$this->assertSame( [ 'alpha,beta', 'gamma,delta' ], $config[ 'options' ][ 'page_params_whitelist' ] );

		$this->assertSame( 30, $con->opts->optGet( 'admin_access_timeout' ) );
		$this->assertSame( [ 'password' ], $con->opts->optGet( 'bot_protection_locations' ) );
		$this->assertSame( 'light', $con->opts->optGet( 'display_plugin_badge' ) );
		$this->assertSame( 'Y', $con->opts->optGet( 'enable_tracking' ) );
		$this->assertSame( [ 'admin.php,existing_param' ], $con->opts->optGet( 'page_params_whitelist' ) );
	}

	public function test_profile_catalog_excludes_sync_internal_state_and_hidden_options() :void {
		$con = $this->requireController();
		$keys = ( new ProfileOptionsCatalog() )->profileableKeys();

		foreach ( [
			'global_enable_plugin_features',
			'import_id',
			'import_url_ids',
			'importexport_enable',
			'importexport_handshake_expires_at',
			'importexport_masterurl',
			'importexport_network_invite_block_until',
			'importexport_pending_network_invites',
			'importexport_secretkey',
			'importexport_secretkey_expires_at',
			'importexport_sites_migrated_at',
			'enable_live_log',
			NetworkInviteRepository::OPTION_KEY,
			NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY,
			'xfer_excluded',
		] as $internalKey ) {
			$this->assertNotContains( $internalKey, $keys );
		}

		foreach ( $keys as $key ) {
			$this->assertNotSame( 'section_hidden', $con->opts->optDef( $key )[ 'section' ] ?? '' );
		}
		$this->assertContains( 'display_plugin_badge', $keys );
	}

	public function test_invalid_profile_values_do_not_refresh_profile_from_live_options() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->store();

		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );
		$this->assertTrue( $repo->saveOptionValues( $profile, [
			'visitor_address_source' => 'AUTO_DETECT_IP',
		] ) );

		$payload = $this->routeRuntime()->processActionPayloadWithAdminBypass( ImportExportProfileOptionsSave::SLUG, [
			'form_enc'    => [ FormParams::ENC_BASE64, FormParams::ENC_JSON ],
			'form_params' => \base64_encode( \wp_json_encode( [
				'all_opts_keys'          => 'visitor_address_source',
				'visitor_address_source' => 'not-a-valid-address-source',
			] ) ),
		] );
		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );

		$profile = $repo->findById( $profile->id );
		$this->assertNotEmpty( $profile );
		$this->assertSame( 'AUTO_DETECT_IP', $repo->configForProfile( $profile )[ 'options' ][ 'visitor_address_source' ] );
		$this->assertSame( 'REMOTE_ADDR', $con->opts->optGet( 'visitor_address_source' ) );
	}

	private function routeRuntime() :PluginAdminRouteRuntime {
		return new PluginAdminRouteRuntime();
	}

	private function newImportExportProfilesHandler( bool $useReadyCache ) :ProfilesDB {
		$con = $this->requireController();
		$dbDef = $con->db_con->getHandlers()[ ProfilesDB::DB_KEY ][ 'def' ];
		$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );
		$handler = new ProfilesDB( $dbDef );
		$handler->use_table_ready_cache = $useReadyCache;
		return $handler;
	}

	private function dropImportExportProfilesTable( bool $resetDbCon = true ) :void {
		global $wpdb;

		$table = $this->requireController()->db_con->import_export_profiles->getTable();
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		Services::WpDb()->clearResultShowTables();
		if ( $resetDbCon ) {
			$this->requireController()->db_con->reset();
		}
	}

	private function assertProfileRenderGroupsCoverProfileableKeysOnce( array $groups ) :void {
		$renderedKeys = [];
		foreach ( $groups as $group ) {
			$this->assertIsArray( $group );
			$this->assertArrayHasKey( 'sections', $group );
			$this->assertIsArray( $group[ 'sections' ] );
			foreach ( $group[ 'sections' ] as $section ) {
				$this->assertIsArray( $section );
				$this->assertArrayHasKey( 'options', $section );
				$this->assertIsArray( $section[ 'options' ] );
				foreach ( $section[ 'options' ] as $option ) {
					$this->assertIsArray( $option );
					$this->assertArrayHasKey( 'key', $option );
					$this->assertIsString( $option[ 'key' ] );
					$renderedKeys[] = $option[ 'key' ];
				}
			}
		}

		$profileableKeys = ( new ProfileOptionsCatalog() )->profileableKeys();
		$this->assertNotEmpty( $groups );
		$this->assertSame( \count( $renderedKeys ), \count( \array_unique( $renderedKeys ) ) );
		$this->assertEqualsCanonicalizing( $profileableKeys, $renderedKeys );
	}
}
