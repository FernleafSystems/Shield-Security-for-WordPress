<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return int
	 */
	public function getAutoExpireTime() {
		return constant( strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	/**
	 * @return array
	 */
	public function getAutoUnblockIps() {
		$aIps = $this->getOpt( 'autounblock_ips', [] );
		return is_array( $aIps ) ? $aIps : [];
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function getCanIpRequestAutoUnblock( $sIp ) {
		$aExistingIps = $this->getAutoUnblockIps();
		return !array_key_exists( $sIp, $aExistingIps )
			   || ( Services::Request()->carbon()->subDay( 1 ) > $aExistingIps[ $sIp ] );
	}

	/**
	 * @return int
	 */
	public function getOffenseLimit() {
		return (int)$this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_IPs() {
		return $this->getDef( 'ip_list_table_columns' );
	}
	/**
	 * @return string
	 */
	public function getDbTable_IPs() {
		return $this->getCon()->prefixOption( $this->getDef( 'ip_lists_table_name' ) );
	}

	/**
	 * @return bool
	 */
	public function isEnabledAutoBlackList() {
		return $this->getOffenseLimit() > 0;
	}

	/**
	 * @return bool
	 */
	public function isEnabledAutoUserRecover() {
		return !$this->isOpt( 'user_auto_recover', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrack404() {
		return $this->isSelectOptionEnabled( 'track_404' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackFakeWebCrawler() {
		return $this->isSelectOptionEnabled( 'track_fakewebcrawler' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackLoginInvalid() {
		return $this->isSelectOptionEnabled( 'track_logininvalid' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackLoginFailed() {
		return $this->isSelectOptionEnabled( 'track_loginfailed' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackLinkCheese() {
		return $this->isSelectOptionEnabled( 'track_linkcheese' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackXmlRpc() {
		return $this->isSelectOptionEnabled( 'track_xmlrpc' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	public function isTrackOptTransgression( $sOptionKey ) {
		return strpos( $this->getOpt( $sOptionKey ), 'transgression' ) !== false;
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	public function isTrackOptDoubleTransgression( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'transgression-double' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	public function isTrackOptImmediateBlock( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'block' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	protected function isSelectOptionEnabled( $sOptionKey ) {
		$bOptPrem = $this->isOptPremium( $sOptionKey );
		return ( !$bOptPrem || $this->getCon()->isPremiumActive() ) && !$this->isOpt( $sOptionKey, 'disabled' );
	}
}