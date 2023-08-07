<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1826() {
		if ( get_user_count() > 10000 ) {
			( new Lib\Snapshots\Ops\Delete() )->delete( Auditors\Users::Slug() );
		}
	}
}