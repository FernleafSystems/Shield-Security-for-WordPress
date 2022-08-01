<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Services\Services;

class IsEmailTrusted {

	/**
	 * @param string $email
	 * @param int    $nMinimumApproved
	 * @param array  $trustedRoles
	 * @return bool
	 */
	public function trusted( $email, $nMinimumApproved = 1, $trustedRoles = [] ) {
		$trusted = Services::WpComments()->countApproved( $email ) >= $nMinimumApproved;

		if ( !$trusted && !empty( $trustedRoles ) ) {
			$user = Services::WpUsers()->getUserByEmail( $email );
			if ( $user instanceof \WP_User ) {
				$trusted = count( array_intersect( $trustedRoles, array_map( 'strtolower', $user->roles ) ) ) > 0;
			}
		}

		return $trusted;
	}
}