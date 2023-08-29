<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IsEmailTrusted {

	use ModConsumer;

	public function roleTrusted( \WP_User $user ) :bool {
		return \count(
				   \array_intersect( $this->opts()->getTrustedRoles(), \array_map( '\strtolower', $user->roles ) )
			   ) > 0;
	}

	public function emailTrusted( string $email ) :bool {
		return Services::WpComments()->countApproved( $email ) >= $this->opts()->getApprovedMinimum();
	}
}