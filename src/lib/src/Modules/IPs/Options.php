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

	public function getCanIpRequestAutoUnblock( string $ip ) :bool {
		$existing = $this->getAutoUnblockIps();
		return !array_key_exists( $ip, $existing )
			   || ( Services::Request()->carbon()->subHour( 1 )->timestamp > $existing[ $ip ] );
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

	public function isEnabledAutoBlackList() :bool {
		return $this->getOffenseLimit() > 0;
	}

	public function isEnabledAutoVisitorRecover() :bool {
		return in_array( 'gasp', (array)$this->getOpt( 'user_auto_recover', [] ) );
	}

	public function isEnabledMagicEmailLinkRecover() :bool {
		return in_array( 'email', (array)$this->getOpt( 'user_auto_recover', [] ) );
	}

	public function isEnabledTrack404() :bool {
		return $this->isSelectOptionEnabled( 'track_404' );
	}

	public function isEnabledTrackFakeWebCrawler() :bool {
		return $this->isSelectOptionEnabled( 'track_fakewebcrawler' );
	}

	public function isEnabledTrackInvalidScript() :bool {
		return $this->isSelectOptionEnabled( 'track_invalidscript' );
	}

	public function isEnabledTrackLoginInvalid() :bool {
		return $this->isSelectOptionEnabled( 'track_logininvalid' );
	}

	public function isEnabledTrackLoginFailed() :bool {
		return $this->isSelectOptionEnabled( 'track_loginfailed' );
	}

	public function isEnabledTrackLinkCheese() :bool {
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

	protected function isSelectOptionEnabled( string $key ) :bool {
		return !$this->isOpt( $key, 'disabled' );
	}
}