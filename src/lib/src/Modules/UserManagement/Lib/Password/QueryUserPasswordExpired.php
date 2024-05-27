<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueryUserPasswordExpired {

	use PluginControllerConsumer;

	public function check( \WP_User $user ) :bool {
		$meta = self::con()->user_metas->for( $user );
		$expireTime = self::con()->comps->opts_lookup->getPassExpireTimeout();
		$passStartAt = $meta->record->pass_started_at;
		$isExpired = $expireTime > 0 && $passStartAt > 0 && ( Services::Request()->ts() - $passStartAt > $expireTime );
		return apply_filters( 'shield/user/is_user_password_expired', $isExpired, $user, $meta, $passStartAt, $expireTime );
	}
}