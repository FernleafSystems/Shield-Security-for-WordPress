<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class OptionSaveCorrectionsIntegrationTest extends ShieldIntegrationTestCase {

	private const PREMIUM_CAPABILITIES = [
		'scan_file_locker',
		'http_headers_csp',
		'user_suspension',
		'user_block_spam_registration',
	];

	private const SNAPSHOT_KEYS = [
		'api_namespace_exclusions',
		'importexport_masterurl',
		'rename_wplogin_path',
		'rename_wplogin_redirect',
		'mfa_user_setup_pages',
		'sec_admin_users',
		'trusted_user_roles',
		'auto_idle_roles',
		'email_checks',
		'xcsp_custom',
		'enable_x_content_security_policy',
		'page_params_whitelist',
		'request_whitelist',
		'importexport_whitelist',
		'file_locker',
	];

	private array $originalOptions = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( self::PREMIUM_CAPABILITIES );
		$con = $this->requireController();
		foreach ( self::SNAPSHOT_KEYS as $key ) {
			$this->originalOptions[ $key ] = $con->opts->optGet( $key );
		}
	}

	public function tear_down() {
		$con = static::con();
		if ( $con !== null ) {
			foreach ( $this->originalOptions as $key => $value ) {
				$con->opts->optSet( $key, $value );
			}
			if ( $con->opts->hasChanges() ) {
				$con->opts->store();
			}
		}
		parent::tear_down();
	}

	public function test_save_applies_core_option_corrections() :void {
		$con = $this->requireController();

		$con->opts
			->optSet( 'api_namespace_exclusions', [ 'woocommerce' ] )
			->optSet( 'importexport_masterurl', 'not a valid url' )
			->optSet( 'rename_wplogin_path', '///My Login-Path!!!///' )
			->optSet( 'rename_wplogin_redirect', 'https://example.com/login/next-step?foo=1' )
			->optSet( 'mfa_user_setup_pages', [] )
			->store();

		$this->assertSame( [ 'woocommerce', 'shield' ], $con->opts->optGet( 'api_namespace_exclusions' ) );
		$this->assertSame( '', $con->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 'MyLogin-Path', $con->opts->optGet( 'rename_wplogin_path' ) );

		$redirect = (string)$con->opts->optGet( 'rename_wplogin_redirect' );
		$this->assertStringStartsWith( '/', $redirect );
		$this->assertStringNotContainsString( '://', $redirect );
		$this->assertSame( [ 'profile' ], $con->opts->optGet( 'mfa_user_setup_pages' ) );
	}

	public function test_security_and_content_corrections_are_applied_during_store() :void {
		$con = $this->requireController();
		$userId = $this->createAdministratorUser( [
			'user_login' => 'secadmin_target',
			'user_email' => 'secadmin-target@example.com',
		] );
		$user = get_user_by( 'id', $userId );

		$con->opts
			->optSet( 'sec_admin_users', [ 'not-an-admin', (string)$user->user_email, '999999' ] )
			->optSet( 'trusted_user_roles', [ 'Administrator', 'editor ', '<script>' ] )
			->optSet( 'auto_idle_roles', [ ' Administrator ', 'bad<>role', 'editor' ] )
			->optSet( 'email_checks', [ 'domain_registered', 'domain_registered' ] )
			->optSet( 'xcsp_custom', [ 'default-src self;', '  img-src https://example.com  ', '' ] )
			->optSet( 'page_params_whitelist', [ ' /index.php, Foo , foo, Bar ' ] )
			->store();

		$this->assertSame( [ 'secadmin_target' ], $con->opts->optGet( 'sec_admin_users' ) );
		$this->assertSame( [ 'administrator', 'editor', 'script' ], $con->opts->optGet( 'trusted_user_roles' ) );
		$this->assertSame( [ 'administrator', 'badrole', 'editor' ], $con->opts->optGet( 'auto_idle_roles' ) );
		$this->assertSame( [ 'domain_registered', 'syntax' ], \array_values( $con->opts->optGet( 'email_checks' ) ) );
		$this->assertSame( [ 'default-src self;', 'img-src https://example.com;' ], $con->opts->optGet( 'xcsp_custom' ) );
		$pageWhitelist = $con->comps->opts_lookup->getFirewallParametersWhitelist();
		$this->assertArrayHasKey( '/index.php', $pageWhitelist );
		$this->assertSame( [ 'foo', 'bar' ], \array_values( \array_unique( $pageWhitelist[ '/index.php' ] ) ) );
	}

	public function test_list_corrections_and_empty_csp_rules_are_applied_during_store() :void {
		$con = $this->requireController();

		$con->opts
			->optSet( 'enable_x_content_security_policy', 'Y' )
			->optSet( 'xcsp_custom', [ '', '   ' ] )
			->optSet( 'request_whitelist', [ '/', '/custom-path', home_url( '/' ) ] )
			->optSet( 'importexport_whitelist', [ 'https://allowed.example.com', 'invalid-value' ] )
			->optSet( 'file_locker', [ 'wpconfig', 'root_webconfig' ] )
			->store();

		$this->assertSame( 'N', $con->opts->optGet( 'enable_x_content_security_policy' ) );
		$this->assertSame( [], $con->opts->optGet( 'xcsp_custom' ) );

		$requestWhitelist = $con->opts->optGet( 'request_whitelist' );
		$this->assertContains( '/custom-path', $requestWhitelist );
		$this->assertNotContains( '/', $requestWhitelist );
		$this->assertSame( [ 'https://allowed.example.com' ], $con->opts->optGet( 'importexport_whitelist' ) );

		$fileLocker = $con->opts->optGet( 'file_locker' );
		$this->assertContains( 'wpconfig', $fileLocker );
		if ( !Services::Data()->isWindows() ) {
			$this->assertNotContains( 'root_webconfig', $fileLocker );
		}
	}

	public function test_valid_master_url_survives_store() :void {
		$con = $this->requireController();
		$url = 'https://master.example.com';

		$con->opts->optSet( 'importexport_masterurl', $url )->store();

		$this->assertSame( $url, $con->opts->optGet( 'importexport_masterurl' ) );
	}
}
