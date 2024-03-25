<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1907() {
		if ( !self::con()->isPremiumActive() ) {
			self::con()->opts->optReset( 'session_lock' );
		}
	}
}