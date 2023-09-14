<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\LicenseScheduleCheck;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1820() {
		if ( self::con()->isPremiumActive() ) {
			try {
				self::con()->action_router->action( LicenseScheduleCheck::class );
			}
			catch ( ActionException $e ) {
			}
		}
	}
}