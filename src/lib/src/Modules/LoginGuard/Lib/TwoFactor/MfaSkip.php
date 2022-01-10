<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class MfaSkip {

	use Shield\Modules\ModConsumer;

	/**
	 * @param \WP_User $user
	 */
	public function addMfaSkip( \WP_User $user ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$meta = $this->getCon()->getUserMeta( $user );
		$hashes = is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [];
		$hashes[ $this->getAgentHash() ] = Services::Request()->ts();

		$maxExpires = $opts->getMfaSkip();
		if ( $maxExpires > 0 ) {
			$hashes = array_filter( $hashes,
				function ( $ts ) use ( $maxExpires ) {
					return Services::Request()->ts() - $ts < $maxExpires;
				}
			);
		}

		$meta->hash_loginmfa = $hashes;
	}

	public function canMfaSkip( \WP_User $user ) :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$canSkip = false;

		if ( $opts->isMfaSkip() ) {
			$agentHash = $this->getAgentHash();
			$meta = $this->getCon()->getUserMeta( $user );
			$hashes = is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [];
			$canSkip = isset( $hashes[ $agentHash ] )
					   && ( (int)$hashes[ $agentHash ] + $opts->getMfaSkip() ) > $req->ts();
		}

		return $canSkip;
	}

	private function getAgentHash() :string {
		return md5( serialize( [
			Services::IP()->getRequestIp(),
			Services::Request()->getUserAgent()
		] ) );
	}
}