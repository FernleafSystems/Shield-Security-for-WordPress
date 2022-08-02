<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Services\Services;

class IsEmailTrusted {

	public function trusted( string $email, int $minimumApproved = 1, array $trustedRoles = [] ) :bool {
		$trusted = Services::WpComments()->countApproved( $email ) >= $minimumApproved;

		if ( !$trusted && !empty( $trustedRoles ) ) {
			$user = Services::WpUsers()->getUserByEmail( $email );
			if ( $user instanceof \WP_User ) {
				$trusted = count( array_intersect( $trustedRoles, array_map( 'strtolower', $user->roles ) ) ) > 0;
			}
		}

		return $trusted;
	}
}