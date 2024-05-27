<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueryUserPasswordExpired {

	use PluginControllerConsumer;

	public function check( \WP_User $user ) :bool {
		$expireTTL = self::con()->comps->opts_lookup->getPassExpireTimeout();
		$passAge = Services::Request()->ts() - self::con()->user_metas->for( $user )->record->pass_started_at;
		$isExpired = $expireTTL > 0 && $passAge > 0 && ( $passAge > $expireTTL );
		return apply_filters( 'shield/user/is_user_password_expired', $isExpired, $user, $passAge, $expireTTL );
	}
}