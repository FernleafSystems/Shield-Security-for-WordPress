<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	use WpLoginCapture;

	/**
	 * @var Session\EntryVO
	 */
	private $current;

	protected function run() {
		if ( !Services::WpUsers()->isProfilePage() && !Services::IP()->isLoopback() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getSessionCon()->terminateCurrentSession();
			}, 0 );
		}

		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );

		$this->setupLoginCaptureHooks();
		$this->setToCaptureApplicationLogin( true )
			 ->setAllowMultipleCapture( true );
	}

	protected function captureLogin( \WP_User $user ) {
		if ( !empty( $this->getLoggedInCookie() ) ) {
			$sessonCon = $this->getCon()->getModule_Sessions()->getSessionCon();
			$sessonCon->terminateCurrentSession();
			$sessonCon->createSession( $user, $this->getLoggedInCookie() );
			$this->getCon()->fireEvent( 'login_success' );
		}
	}

	public function onWpInit() {
		$this->autoAddSession();
	}

	public function onWpLoaded() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( $mod->getSessionCon()->hasSession() ) {
			/** @var Session\Update $update */
			$update = $mod->getDbHandler_Sessions()->getQueryUpdater();
			$update->updateLastActivity( $mod->getSessionCon()->getCurrent() );
		}
	}

	private function autoAddSession() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$sessCon = $mod->getSessionCon();
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $user instanceof \WP_User && !$sessCon->hasSession() ) {
			$sessCon->createSession( $user );
		}
	}

	/**
	 * Only show Go To Admin link for Authors and above.
	 * @param string $msg
	 * @return string
	 * @throws \Exception
	 */
	public function printLinkToAdmin( $msg = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$user = Services::WpUsers()->getCurrentWpUser();

		if ( in_array( Services::Request()->query( 'action' ), [ '', 'login' ] )
			 && ( $user instanceof \WP_User ) && $mod->getSessionCon()->hasSession() ) {
			$msg .= sprintf( '<p class="message">%s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' )
				.sprintf( ' <span style="white-space: nowrap">(%s)</span>', $user->user_login ),
				( $user->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}
		return $msg;
	}

	protected function getWpHookPriority( string $hook ) :int {
		switch ( $hook ) {
			case 'init':
				$pri = 1;
				break;
			default:
				$pri = parent::getWpHookPriority( $hook );
		}
		return $pri;
	}

	protected function getHookPriority() :int {
		return 100;
	}
}