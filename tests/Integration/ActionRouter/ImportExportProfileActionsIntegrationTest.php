<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ImportExportProfileOptionIncludeToggle,
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
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertStringContainsString( 'data-context="import_export_profile"', $html );
		$this->assertStringContainsString( 'data-options-save-action="profile_form_save"', $html );
		$this->assertStringContainsString( 'data-transfer-action="profile_xfer_include_toggle"', $html );
		$this->assertStringContainsString( 'name="all_opts_keys"', $html );
	}

	public function test_profile_save_and_include_toggle_update_primary_profile_only() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'display_plugin_badge', 'light' )
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->optSet( 'enable_tracking', 'Y' )
			->store();

		$repo = new ProfileRepository();
		$profile = $repo->ensurePrimaryProfile();
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
		$profile = $repo->ensurePrimaryProfile();
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
		$profile = $repo->ensurePrimaryProfile();
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
}
