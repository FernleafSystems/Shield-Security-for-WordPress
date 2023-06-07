<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class MfaSkip {

	use LoginGuard\ModConsumer;

	public function addMfaSkip( \WP_User $user ) {
		$meta = $this->con()->user_metas->for( $user );
		$hashes = is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [];
		$hashes[ $this->getAgentHash() ] = Services::Request()->ts();

		$maxExpires = $this->opts()->getMfaSkip();
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
		$canSkip = false;

		if ( $this->opts()->isMfaSkip() ) {
			$agentHash = $this->getAgentHash();
			$meta = $this->con()->user_metas->for( $user );
			$hashes = is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [];
			$canSkip = isset( $hashes[ $agentHash ] )
					   && ( (int)$hashes[ $agentHash ] + $this->opts()->getMfaSkip() ) > Services::Request()->ts();
		}

		return $canSkip;
	}

	private function getAgentHash() :string {
		$hashData = apply_filters( 'shield/2fa_remember_me_params', $this->getDefaultHashParams() );
		return md5( serialize( empty( $hashData ) ? $this->getDefaultHashParams() : $hashData ) );
	}

	private function getDefaultHashParams() :array {
		return [
			'ip'         => $this->con()->this_req->ip,
			'user_agent' => Services::Request()->getUserAgent()
		];
	}
}