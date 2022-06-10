<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
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
		$ips = is_array( $this->getOpt( 'autounblock_ips', [] ) ) ? $this->getOpt( 'autounblock_ips', [] ) : [];
		$ips = array_filter( $ips, function ( $ts ) {
			return Services::Request()
						   ->carbon()
						   ->subHours( 1 )->timestamp < $ts;
		} );
		$this->setOpt( 'autounblock_ips', $ips );
		return $ips;
	}

	public function getAutoUnblockEmailIDs() :array {
		$ips = $this->getOpt( 'autounblock_emailids', [] );
		return is_array( $ips ) ? $ips : [];
	}

	public function getCanIpRequestAutoUnblock( string $ip ) :bool {
		return !array_key_exists( $ip, $this->getAutoUnblockIps() );
	}

	public function getCanRequestAutoUnblockEmailLink( \WP_User $user ) :bool {
		$existing = $this->getAutoUnblockEmailIDs();
		return !array_key_exists( $user->ID, $existing )
			   || ( Services::Request()->carbon()->subHours( 1 )->timestamp > $existing[ $user->ID ] );
	}

	public function getOffenseLimit() :int {
		return (int)$this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
	 */
	public function getRequestWhitelistAsRegex() :array {
		$paths = $this->isPremium() ? $this->getOpt( 'request_whitelist', [] ) : [];
		return array_map(
			function ( $value ) {
				return ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::URL_PATH );
			},
			is_array( $paths ) ? $paths : []
		);
	}

	public function getAntiBotMinimum() :int {
		return (int)$this->getOpt( 'antibot_minimum', 50 );
	}

	public function getAntiBotHighReputationMinimum() :int {
		return (int)$this->getOpt( 'antibot_high_reputation_minimum', 200 );
	}

	public function isEnabledAntiBotEngine() :bool {
		return $this->getAntiBotMinimum() > 0;
	}

	public function isEnabledAutoBlackList() :bool {
		return $this->getOffenseLimit() > 0;
	}

	public function isEnabledCrowdSecAutoBlock() :bool {
		return !$this->isOpt( 'cs_block', 'disabled' );
	}

	public function isEnabledCrowdSecAutoVisitorUnblock() :bool {
		return $this->isOpt( 'cs_block', 'block_with_unblock' );
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

	public function isEnabledTrackXmlRpc() :bool {
		return $this->isSelectOptionEnabled( 'track_xmlrpc' );
	}

	public function getOffenseCountFor( string $key ) :int {
		if ( $this->isTrackOptDoubleTransgression( $key ) ) {
			$count = 2;
		}
		elseif ( $this->isTrackOptTransgression( $key ) || $this->isTrackOptImmediateBlock( $key ) ) {
			$count = 1;
		}
		else {
			$count = 0;
		}
		return $count;
	}

	public function isTrackOptTransgression( string $key ) :bool {
		return strpos( $this->getOpt( $key ), 'transgression' ) !== false;
	}

	public function isTrackOptDoubleTransgression( string $key ) :bool {
		return $this->isOpt( $key, 'transgression-double' );
	}

	public function isTrackOptImmediateBlock( string $key ) :bool {
		return $this->isOpt( $key, 'block' );
	}

	protected function isSelectOptionEnabled( string $key ) :bool {
		return !$this->isOpt( $key, 'disabled' );
	}
}