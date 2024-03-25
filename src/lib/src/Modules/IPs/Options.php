<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getAutoExpireTime() :int {
		return (int)constant( \strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	/**
	 * @deprecated 19.1
	 */
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

	/**
	 * @deprecated 19.1
	 */
	public function isTrackOptTransgression( string $key ) :bool {
		return \strpos( $this->getOpt( $key ), 'transgression' ) !== false;
	}

	/**
	 * @deprecated 19.1
	 */
	public function isTrackOptDoubleTransgression( string $key ) :bool {
		return $this->isOpt( $key, 'transgression-double' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isTrackOptImmediateBlock( string $key ) :bool {
		return $this->isOpt( $key, 'block' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function botSignalsGetAllowable404s() :array {
		$def = $this->getDef( 'bot_signals' )[ 'allowable_ext_404s' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_extensions_404s', $def ),
			function ( $ext ) {
				return !empty( $ext ) && \is_string( $ext ) && \preg_match( '#^[a-z\d]+$#i', $ext );
			}
		) );
	}

	/**
	 * @deprecated 19.1
	 */
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
	 * @deprecated 19.1
	 */
	public function isEnabledCrowdSecAutoBlock() :bool {
		return !$this->isOpt( 'cs_block', 'disabled' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledAutoBlackList() :bool {
		return $this->getOffenseLimit() > 0;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOffenseLimit() :int {
		return (int)$this->getOpt( 'transgression_limit' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getAntiBotMinimum() :int {
		return (int)$this->getOpt( 'antibot_minimum', 50 );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledAntiBotEngine() :bool {
		return $this->getAntiBotMinimum() > 0;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getAntiBotHighReputationMinimum() :int {
		return (int)$this->getOpt( 'antibot_high_reputation_minimum', 200 );
	}
}