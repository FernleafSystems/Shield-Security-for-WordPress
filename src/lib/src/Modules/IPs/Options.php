<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

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
	 * @return array
	 */
	public function getAutoUnblockEmailIDs() {
		$aIps = $this->getOpt( 'autounblock_emailids', [] );
		return is_array( $aIps ) ? $aIps : [];
	}

	/**
	 * @param string $ip
	 * @return bool
	 */
	public function getCanIpRequestAutoUnblock( $ip ) {
		$existing = $this->getAutoUnblockIps();
		return !array_key_exists( $ip, $existing )
			   || ( Services::Request()->carbon()->subDay( 1 )->timestamp > $existing[ $ip ] );
	}

	/**
	 * @param \WP_User $user
	 * @return bool
	 */
	public function getCanRequestAutoUnblockEmailLink( \WP_User $user ) {
		$existing = $this->getAutoUnblockEmailIDs();
		return !array_key_exists( $user->ID, $existing )
			   || ( Services::Request()->carbon()->subHour( 1 )->timestamp > $existing[ $user->ID ] );
	}

	/**
	 * @return int
	 */
	public function getOffenseLimit() {
		return (int)$this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
	 */
	public function getRequestWhitelistAsRegex() {
		return array_map(
			function ( $sRule ) {
				return sprintf( '#^%s$#i', str_replace( 'STAR', '.*', preg_quote( str_replace( '*', 'STAR', $sRule ), '#' ) ) );
			},
			$this->isPremium() ? $this->getOpt( 'request_whitelist', [] ) : []
		);
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
	public function isEnabledAutoVisitorRecover() {
		return in_array( 'gasp', (array)$this->getOpt( 'user_auto_recover', [] ) );
	}

	/**
	 * @return bool
	 */
	public function isEnabledMagicEmailLinkRecover() {
		return in_array( 'email', (array)$this->getOpt( 'user_auto_recover', [] ) );
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
	 * @param string $key
	 * @return bool
	 */
	public function isTrackOptTransgression( $key ) {
		return strpos( $this->getOpt( $key ), 'transgression' ) !== false;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function isTrackOptDoubleTransgression( $key ) {
		return $this->isOpt( $key, 'transgression-double' );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function isTrackOptImmediateBlock( $key ) {
		return $this->isOpt( $key, 'block' );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	protected function isSelectOptionEnabled( $key ) {
		return !$this->isOpt( $key, 'disabled' );
	}
}