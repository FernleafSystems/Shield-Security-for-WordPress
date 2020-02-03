<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class EmailValidate {

	use ModConsumer;

	public function run() {
		add_filter( 'wp_pre_insert_user_data', [ $this, 'validateNewUserEmail' ] );
	}

	/**
	 * @param array $aUserData
	 * @return array
	 */
	private function validateNewUserEmail( $aUserData ) {
		$sEmail = $aUserData[ 'user_email' ];
		// validate email, return null, log, die? email?
		return $aUserData;
	}
}