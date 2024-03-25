<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IsEmailTrusted {

	use PluginControllerConsumer;

	public function roleTrusted( \WP_User $user ) :bool {
		return \count( \array_intersect(
				self::con()->comps->opts_lookup->getCommentTrustedRoles(), \array_map( '\strtolower', $user->roles )
			) ) > 0;
	}

	public function emailTrusted( string $email ) :bool {
		return Services::WpComments()->countApproved( $email )
			   >= self::con()->comps->opts_lookup->getCommenterTrustedMinimum();
	}
}