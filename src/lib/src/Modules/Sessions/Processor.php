<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getSessionCon()->execute();
		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
	}

	/**
	 * Only show Go To Admin link for Authors+
	 * @param string $msg
	 */
	public function printLinkToAdmin( $msg = '' ) :string {
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

		return is_string( $msg ) ? $msg : '';
	}
}