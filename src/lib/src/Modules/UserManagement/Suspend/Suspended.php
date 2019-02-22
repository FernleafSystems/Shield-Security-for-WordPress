<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

class Suspended extends Base {

	/**
	 * @param null|\WP_User|\WP_Error $oUserOrError
	 * @return \WP_User|\WP_Error
	 */
	public function checkUser( $oUserOrError ) {
		if ( $oUserOrError instanceof \WP_User ) {
			$oMeta = $this->getCon()->getUserMeta( $oUserOrError );
			if ( $oMeta->is_hard_suspended === true ) {
				$oUserOrError = new \WP_Error(
					$this->getCon()->prefix( 'hard-suspended' ),
					'Sorry, this account is suspended. Please contact your website administrator to resolve this.'
				);
			}
		}
		return $oUserOrError;
	}
}