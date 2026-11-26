<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DismissAdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\IpStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * No restrictions apply to a request from a private or local address. The person making it is told so, until they
 * dismiss it for themselves.
 */
class IpStatusNoticeIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	private string $ipBefore = '';

	private bool $isPublicBefore = false;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$req = $this->requireController()->this_req;
		$this->ipBefore = (string)$req->ip;
		$this->isPublicBefore = (bool)$req->ip_is_public;
		// A user of this test's own: what one test's user dismissed must not carry to the next.
		$this->loginAsSecurityAdmin( [ 'role' => 'administrator' ] );
	}

	public function tear_down() {
		$this->requestComesFrom( $this->ipBefore, $this->isPublicBefore );
		parent::tear_down();
	}

	public function test_a_private_address_is_reported_until_the_user_dismisses_it() :void {
		$this->requestComesFrom( '192.168.1.20', false );

		$notice = ( new IpStatus() )->check();
		$this->assertSame( IpStatus::ID_NOT_PUBLIC, $notice[ 'id' ] ?? null );
		$this->assertTrue( $notice[ 'can_dismiss' ] );

		$dismissed = $this->processActionPayloadWithAdminBypass( DismissAdminNotice::SLUG, [ 'notice_id' => IpStatus::ID_NOT_PUBLIC ] );
		$this->assertTrue( $dismissed[ 'success' ] ?? false );
		$this->assertNull( ( new IpStatus() )->check() );

		// Dismissing is each person's own choice: the next admin is still told.
		$this->loginAsSecurityAdmin( [ 'role' => 'administrator' ] );
		$this->assertSame( IpStatus::ID_NOT_PUBLIC, ( new IpStatus() )->check()[ 'id' ] ?? null );
	}

	public function test_a_public_address_is_not_reported() :void {
		$this->requestComesFrom( '203.0.113.60', true );

		$this->assertNull( ( new IpStatus() )->check() );
	}

	private function requestComesFrom( string $ip, bool $isPublic ) :void {
		$req = $this->requireController()->this_req;
		$req->ip = $ip;
		$req->ip_is_public = $isPublic;
	}
}
