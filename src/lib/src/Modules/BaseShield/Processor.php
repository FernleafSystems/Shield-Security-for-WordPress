<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

abstract class Processor extends Base\Processor {

	const RECAPTCHA_JS_HANDLE = 'icwp-google-recaptcha';

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		$con = $this->getCon();
		add_filter( $con->prefix( 'collect_tracking_data' ), [ $this, 'tracking_DataCollect' ] );
	}

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

	/**
	 * Filter used to collect plugin data for tracking.  Fired from the plugin processor only if the option is enabled
	 * - it is not enabled by default.
	 * Note that in this case we "mask" options that have been identified as "sensitive" - i.e. could contain
	 * identifiable data.
	 *
	 * @param $data
	 * @return array
	 */
	public function tracking_DataCollect( $data ) {
		if ( !is_array( $data ) ) {
			$data = [];
		}
		$oMod = $this->getMod();
		$aOptions = $oMod->collectOptionsForTracking();
		if ( !empty( $aOptions ) ) {
			$data[ $oMod->getSlug() ] = [ 'options' => $oMod->collectOptionsForTracking() ];
		}
		return $data;
	}
}