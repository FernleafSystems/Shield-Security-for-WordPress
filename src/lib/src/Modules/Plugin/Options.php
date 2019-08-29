<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return string
	 */
	public function getDbTable_GeoIp() {
		return $this->getCon()->prefixOption( $this->getDef( 'geoip_table_name' ) );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Notes() {
		return $this->getCon()->prefixOption( $this->getDef( 'db_notes_name' ) );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_GeoIp() {
		return $this->getDef( 'geoip_table_columns' );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_Notes() {
		return $this->getDef( 'db_notes_table_columns' );
	}

	/**
	 * @return array
	 */
	public function getGoogleRecaptchaConfig() {
		$aConfig = [
			'key'            => $this->getOpt( 'google_recaptcha_site_key' ),
			'secret'         => $this->getOpt( 'google_recaptcha_secret_key' ),
			'style'          => $this->getOpt( 'google_recaptcha_style' ),
			'style_override' => !$this->getCon()->isPremiumActive()
		];
		if ( $aConfig[ 'style_override' ] ) {
			$aConfig[ 'style' ] = 'light'; // hard-coded light style for non-pro
		}
		return $aConfig;
	}

	/**
	 * @return bool
	 */
	public function isOnFloatingPluginBadge() {
		return $this->isOpt( 'display_plugin_badge', 'Y' );
	}
}