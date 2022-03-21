<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Services\Services;

class IsEmailTrusted {

	/**
	 * @param string $sEmail
	 * @param int    $nMinimumApproved
	 * @param array  $aTrustedRoles
	 * @return bool
	 */
	public function trusted( $sEmail, $nMinimumApproved = 1, $aTrustedRoles = [] ) {
		$bTrusted = Services::WpComments()->countApproved( $sEmail ) >= $nMinimumApproved;

		if ( !$bTrusted && !empty( $aTrustedRoles ) ) {
			$oUser = Services::WpUsers()->getUserByEmail( $sEmail );
			if ( $oUser instanceof \WP_User ) {
				$bTrusted = count( array_intersect( $aTrustedRoles, array_map( 'strtolower', $oUser->roles ) ) ) > 0;
			}
		}

		return $bTrusted;
	}
}