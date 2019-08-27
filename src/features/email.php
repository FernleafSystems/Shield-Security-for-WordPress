<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_Email extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param array $aMessage
	 * @return array
	 */
	public function customiseMandrill( $aMessage ) {
		if ( empty( $aMessage[ 'text' ] ) ) {
			$aMessage[ 'text' ] = $aMessage[ 'html' ];
		}
		return $aMessage;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		$sLimit = $this->getOpt( 'send_email_throttle_limit' );
		if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
			$sLimit = 0;
		}
		$this->setOpt( 'send_email_throttle_limit', $sLimit );
	}

	/**
	 * @return Shield\Modules\Email\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Email\Options();
	}

	/**
	 * @return Shield\Modules\Email\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Email\Strings();
	}
}