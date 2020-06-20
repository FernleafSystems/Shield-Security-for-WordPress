<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class MfaSkip {

	use Shield\Modules\ModConsumer;

	/**
	 * @param \WP_User $oUser
	 */
	public function addMfaSkip( \WP_User $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$oMeta = $this->getCon()->getUserMeta( $oUser );
		$aHashes = is_array( $oMeta->hash_loginmfa ) ? $oMeta->hash_loginmfa : [];
		$aHashes[ $this->getAgentHash() ] = Services::Request()->ts();

		$nMaxExpires = $oOpts->getMfaSkip();
		if ( $nMaxExpires > 0 ) {
			$aHashes = array_filter( $aHashes,
				function ( $nTS ) use ( $nMaxExpires ) {
					return Services::Request()->ts() - $nTS < $nMaxExpires;
				}
			);
		}

		$oMeta->hash_loginmfa = $aHashes;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function canMfaSkip( \WP_User $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oReq = Services::Request();

		$bCanSkip = false;

		if ( $oOpts->isMfaSkip() ) {
			$sAgentHash = $this->getAgentHash();
			$oMeta = $this->getCon()->getUserMeta( $oUser );
			$aHashes = is_array( $oMeta->hash_loginmfa ) ? $oMeta->hash_loginmfa : [];
			$bCanSkip = isset( $aHashes[ $sAgentHash ] )
						&& ( (int)$aHashes[ $sAgentHash ] + $oOpts->getMfaSkip() ) > $oReq->ts();
		}

		return $bCanSkip;
	}

	/**
	 * @return string
	 */
	private function getAgentHash() {
		return md5( serialize( [
			Services::IP()->getRequestIp(),
			Services::Request()->getUserAgent()
		] ) );
	}
}