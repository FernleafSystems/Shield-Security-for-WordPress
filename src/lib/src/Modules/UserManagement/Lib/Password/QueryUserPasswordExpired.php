<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueryUserPasswordExpired {

	use ModConsumer;

	public function check( \WP_User $user ) :bool {
		$expired = false;
		$timeout = self::con()->comps->opts_lookup->getPassExpireTimeout();
		if ( $timeout > 0 ) {
			$startedAt = self::con()->user_metas->for( $user )->record->pass_started_at;
			$expired = $startedAt > 0 && ( Services::Request()->ts() - $startedAt > $timeout );
		}
		return $expired;
	}
}