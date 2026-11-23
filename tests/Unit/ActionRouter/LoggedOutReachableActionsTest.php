<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions,
	Actions\BaseAction,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\ListsRegisteredActions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

/**
 * Every action a logged-out visitor can reach from outside. A logged-out visitor's action nonce is the same for
 * every visitor, so it proves nothing about who sent the request: each of these actions must be public by design
 * or check a credential of its own. Adding to this list is a security review decision: give the reason.
 */
class LoggedOutReachableActionsTest extends BaseUnitTest {

	use ListsRegisteredActions;

	private const REVIEWED_LOGGED_OUT = [
		Actions\CaptureNotBot::class                           => 'silentCAPTCHA registration; records bot signals for the requesting IP only.',
		Actions\CaptureNotBotAltcha::class                     => 'Verifies its own ALTCHA proof-of-work solution.',
		Actions\FullPageDisplay\DisplayReport::class           => 'Redirects to the admin report view, which checks access itself.',
		Actions\IpAutoUnblockCrowdsecVisitor::class            => 'Unblocks only the requesting IP, if blocked, under per-IP timing limits.',
		Actions\IpAutoUnblockShieldVisitor::class              => 'Unblocks only the requesting IP, if blocked, under per-IP timing limits.',
		Actions\LicenseHandshakeVerifyKeyless::class           => 'Licence server probe; answers whether its own handshake nonce is valid.',
		Actions\ShieldNetHandshakeVerify::class                => 'ShieldNET server probe; answers whether its own handshake nonce is valid.',
		Actions\LicenseScheduleCheck::class                    => 'Licence server trigger; only schedules a licence check.',
		Actions\MfaEmailAutoLogin::class                       => 'Email login link; verifies the login nonce and the emailed code.',
		Actions\MfaEmailSendIntent::class                      => 'Sends a login code only for a valid login nonce of the user.',
		Actions\MfaLoginVerifyStep::class                      => 'Hands over to the 2FA login capture, which verifies the login nonce and code.',
		Actions\MfaPasskeyAuthenticationStart::class           => 'Login flow; requires a valid login nonce of the user.',
		Actions\PluginBadgeClose::class                        => 'Closes the badge for the requesting visitor only.',
		Actions\PluginImportExport_Export::class               => 'Cross-site export; verifies the site secret inside the export.',
		Actions\PluginImportExport_HandshakeConfirm::class     => 'Cross-site probe; answers whether the export handshake window is open.',
		Actions\PluginImportExport_NetworkInviteRequest::class => 'Cross-site invitation; stored as pending until an admin accepts it.',
		Actions\PluginImportExport_UpdateNotified::class       => 'Cross-site notification from the trusted master; schedules a pull from it.',
	];

	protected function setUp() :void {
		parent::setUp();
		PluginControllerInstaller::install( new LoggedOutReachableActionsControllerStub() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_every_action_reachable_while_logged_out_is_reviewed() :void {
		$reachable = [];
		foreach ( $this->registeredActions() as $action ) {
			if ( !empty( ExternalEntryGuard::DeclaredAccess( $action ) ) && !$this->isUserAuthRequired( $action ) ) {
				$reachable[] = $action;
			}
		}
		$reviewed = \array_keys( self::REVIEWED_LOGGED_OUT );

		$this->assertSame(
			[],
			\array_values( \array_diff( $reachable, $reviewed ) ),
			'Reachable while logged out but not on the reviewed list.'
		);
		$this->assertSame( [], \array_values( \array_diff( $reviewed, $reachable ) ), 'Stale entries on the reviewed list.' );
		foreach ( self::REVIEWED_LOGGED_OUT as $reason ) {
			$this->assertNotSame( '', \trim( $reason ) );
		}
	}

	/**
	 * @param class-string<BaseAction> $action
	 */
	private function isUserAuthRequired( string $action ) :bool {
		$method = new \ReflectionMethod( $action, 'isUserAuthRequired' );
		$method->setAccessible( true );
		return (bool)$method->invoke( ( new \ReflectionClass( $action ) )->newInstanceWithoutConstructor() );
	}
}

class LoggedOutReachableActionsControllerStub extends Controller {

	public function __construct() {
		$this->cfg = (object)[
			'properties' => [
				'base_permissions' => 'manage_options',
			],
		];
	}
}
