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
	 * @deprecated 15.0
	 */
	private $current;

	protected function run() {
		if ( !Services::WpUsers()->isProfilePage() && !Services::IP()->isLoopback() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getSessionCon()->getCurrentWP();
			}, 0 );
		}

		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );

		$this->setupLoginCaptureHooks();
		$this->setToCaptureApplicationLogin( true )
			 ->setAllowMultipleCapture( true );
	}

	protected function captureLogin( \WP_User $user ) {
		if ( !empty( $this->getLoggedInCookie() ) ) {
			$this->getCon()->fireEvent( 'login_success' );
		}
	}

	/**
	 * Only show Go To Admin link for Authors+.
	 * @param string $msg
	 * @return string
	 * @throws \Exception
	 */
	public function printLinkToAdmin( $msg = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$user = Services::WpUsers()->getCurrentWpUser();

		if ( in_array( Services::Request()->query( 'action' ), [ '', 'login' ] ) && $mod->getSessionWP()->valid
			 && $user instanceof \WP_User ) {
			$msg .= sprintf( '<p class="message">%s %s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' ),
				sprintf( '<span style="white-space: nowrap">(%s)</span>', $user->user_login ),
				( $user->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}
		return $msg;
	}

	protected function getHookPriority() :int {
		return 100;
	}
}