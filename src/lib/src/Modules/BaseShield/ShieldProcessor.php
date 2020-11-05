<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ShieldProcessor
 * @package    FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield
 * @deprecated 10.1
 */
class ShieldProcessor extends Base\BaseProcessor {

	const RECAPTCHA_JS_HANDLE = 'icwp-google-recaptcha';

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	protected function isUserSubjectToLoginIntent( $oUser = null ) {
		$bIsSubject = false;

		if ( !$oUser instanceof \WP_User ) {
			$oUser = Services::WpUsers()->getCurrentWpUser();
		}
		if ( $oUser instanceof \WP_User ) {
			$bIsSubject = apply_filters( $this->getCon()->prefix( 'user_subject_to_login_intent' ), false, $oUser );
		}

		return $bIsSubject;
	}
}