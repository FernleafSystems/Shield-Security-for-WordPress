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

	public function getAutoUnblockIps() :array {
		$ips = $this->getOpt( 'autounblock_ips', [] );
		return is_array( $ips ) ? $ips : [];
	}

	public function getAutoUnblockEmailIDs() :array {
		$ips = $this->getOpt( 'autounblock_emailids', [] );
		return is_array( $ips ) ? $ips : [];
	}

	public function getCanIpRequestAutoUnblock( string $ip ) :bool {
		$existing = $this->getAutoUnblockIps();
		return !array_key_exists( $ip, $existing )
			   || ( Services::Request()->carbon()->subHour( 1 )->timestamp > $existing[ $ip ] );
	}

	public function getCanRequestAutoUnblockEmailLink( \WP_User $user ) :bool {
		$existing = $this->getAutoUnblockEmailIDs();
		return !array_key_exists( $user->ID, $existing )
			   || ( Services::Request()->carbon()->subHour( 1 )->timestamp > $existing[ $user->ID ] );
	}

	public function getOffenseLimit() :int {
		return (int)$this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
	 */
	public function getRequestWhitelistAsRegex() {
		return array_map(
			function ( $rule ) {
				return sprintf( '#^%s$#i', str_replace( 'STAR', '.*', preg_quote( str_replace( '*', 'STAR', $rule ), '#' ) ) );
			},
			$this->isPremium() ? $this->getOpt( 'request_whitelist', [] ) : []
		);
	}

	public function getAntiBotMinimum() :int {
		return (int)$this->getOpt( 'antibot_minimum', 50 );
	}

	public function isEnabledAntiBotEngine() :bool {
		return $this->getAntiBotMinimum() > 0;
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
	public function isTrackOptImmediateBlock( $key ) :bool {
		return $this->isOpt( $key, 'block' );
	}

	protected function isSelectOptionEnabled( string $key ) :bool {
		return !$this->isOpt( $key, 'disabled' );
	}
}