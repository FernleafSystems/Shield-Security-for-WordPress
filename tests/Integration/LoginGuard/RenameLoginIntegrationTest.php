<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\Rename\RenameLogin;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class RenameLoginIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionsSnapshot = [];

	private array $requestSnapshot = [];

	private string $permalinkSnapshot = '';

	public function set_up() :void {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'rename_wplogin_path',
			'rename_wplogin_redirect',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->permalinkSnapshot = (string)\get_option( 'permalink_structure' );
		\update_option( 'permalink_structure', '/%postname%/' );
		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/front-page/',
		] );
	}

	public function tear_down() :void {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}
		\update_option( 'permalink_structure', $this->permalinkSnapshot );
		parent::tear_down();
	}

	public function test_login_url_rewrite_preserves_query_contract() :void {
		$this->setRenameLoginOptions( 'shield-login' );

		$rewritten = ( new RenameLogin() )->fCheckForLoginPhp(
			\site_url( 'wp-login.php?action=lostpassword&redirect_to=%2Fwp-admin%2F' )
		);

		$query = [];
		\parse_str( (string)\wp_parse_url( $rewritten, \PHP_URL_QUERY ), $query );

		$this->assertSame( \home_url( 'shield-login' ), \strtok( $rewritten, '?' ) );
		$this->assertSame( 'lostpassword', (string)( $query[ 'action' ] ?? '' ) );
		$this->assertSame( '/wp-admin/', (string)( $query[ 'redirect_to' ] ?? '' ) );
	}

	public function test_unrelated_urls_are_not_rewritten() :void {
		$this->setRenameLoginOptions( 'shield-login' );
		$location = \site_url( 'wp-admin/admin-ajax.php?action=heartbeat' );

		$this->assertSame( $location, ( new RenameLogin() )->fCheckForLoginPhp( $location ) );
	}

	public function test_rename_login_requires_non_empty_supported_custom_path() :void {
		$this->setRenameLoginOptions( 'shield-login' );
		$this->assertTrue( ( new RenameLogin() )->isEnabled() );

		$this->setRenameLoginOptions( '' );
		$this->assertFalse( ( new RenameLogin() )->isEnabled() );
	}

	public function test_runtime_capture_is_disabled_for_bypass_and_xmlrpc_requests() :void {
		$this->setRenameLoginOptions( 'shield-login' );

		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/front-page/',
		], [], [], [
			'request_bypasses_all_restrictions' => false,
			'wp_is_xmlrpc'                      => false,
		] );
		$this->assertTrue( $this->canRunRenameLogin() );

		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/front-page/',
		], [], [], [
			'request_bypasses_all_restrictions' => true,
			'wp_is_xmlrpc'                      => false,
		] );
		$this->assertFalse( $this->canRunRenameLogin() );

		$this->applyCurrentRequestState( [
			'REQUEST_URI' => '/xmlrpc.php',
		], [], [], [
			'request_bypasses_all_restrictions' => false,
			'wp_is_xmlrpc'                      => true,
		] );
		$this->assertFalse( $this->canRunRenameLogin() );
	}

	private function setRenameLoginOptions( string $path ) :void {
		$this->requireController()->opts
			->optSet( 'rename_wplogin_path', $path )
			->optSet( 'rename_wplogin_redirect', '' )
			->store();
	}

	private function canRunRenameLogin() :bool {
		$method = new \ReflectionMethod( RenameLogin::class, 'canRun' );
		$method->setAccessible( true );
		return (bool)$method->invoke( new RenameLogin() );
	}
}
