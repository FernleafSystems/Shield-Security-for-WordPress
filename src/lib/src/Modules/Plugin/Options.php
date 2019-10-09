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
	 * @return array
	 */
	public function getServerIpDetails() {
		$aDetails = $this->getOpt( 'this_server_ip_details', [] );
		if ( empty( $aDetails ) ) {
			$aDetails = [
				'ip'       => '',
				'hash'     => '',
				'check_ts' => Services::Request()->carbon()->subYear()->timestamp,
			];
		}
		return $aDetails;
	}

	/**
	 * @param array $aDetails
	 * @return $this
	 */
	public function updateServerIpDetails( $aDetails ) {
		return $this->setOpt( 'this_server_ip_details', array_merge( $this->getServerIpDetails(), $aDetails ) );
	}

	/**
	 * @return bool
	 */
	public function isOnFloatingPluginBadge() {
		return $this->isOpt( 'display_plugin_badge', 'Y' );
	}

	/**
	 * @param bool $bOnOrOff
	 * @return $this
	 */
	public function setPluginTrackingPermission( $bOnOrOff = true ) {
		return $this->setOpt( 'enable_tracking', $bOnOrOff ? 'Y' : 'N' )
					->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
	}
}