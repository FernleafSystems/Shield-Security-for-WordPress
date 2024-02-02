<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Options {

	public function preSave() :void {
		if ( !\defined( \strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$this->resetOptToDefault( 'auto_expire' );
		}

		if ( $this->isOptChanged( 'request_whitelist' ) ) {
			$WP = Services::WpGeneral();
			$this->setOpt( 'request_whitelist',
				( new WildCardOptions() )->clean(
					$this->getOpt( 'request_whitelist', [] ),
					\array_unique( \array_map(
						function ( $url ) {
							return (string)wp_parse_url( $url, \PHP_URL_PATH );
						},
						[
							'/',
							$WP->getHomeUrl(),
							$WP->getWpUrl(),
							$WP->getAdminUrl( 'admin.php' ),
						]
					) ),
					WildCardOptions::URL_PATH
				)
			);
		}
	}

	public function getAutoExpireTime() :int {
		return (int)constant( \strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	public function getOffenseLimit() :int {
		return (int)$this->getOpt( 'transgression_limit' );
	}

	public function botSignalsGetAllowable404s() :array {
		$def = $this->getDef( 'bot_signals' )[ 'allowable_ext_404s' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_extensions_404s', $def ),
			function ( $ext ) {
				return !empty( $ext ) && \is_string( $ext ) && \preg_match( '#^[a-z\d]+$#i', $ext );
			}
		) );
	}

	public function botSignalsGetAllowableScripts() :array {
		$def = $this->getDef( 'bot_signals' )[ 'allowable_invalid_scripts' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_invalid_scripts', $def ),
			function ( $script ) {
				return !empty( $script ) && \is_string( $script ) && \strpos( $script, '.php' );
			}
		) );
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
	 */
	public function getRequestWhitelistAsRegex() :array {
		$paths = self::con()->isPremiumActive() ? $this->getOpt( 'request_whitelist', [] ) : [];
		return \array_map(
			function ( $value ) {
				return ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::URL_PATH );
			},
			\is_array( $paths ) ? $paths : []
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
		return \in_array( 'gasp', $this->getOpt( 'user_auto_recover', [] ) );
	}

	public function isEnabledMagicEmailLinkRecover() :bool {
		return \in_array( 'email', $this->getOpt( 'user_auto_recover', [] ) );
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
		return \strpos( $this->getOpt( $key ), 'transgression' ) !== false;
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