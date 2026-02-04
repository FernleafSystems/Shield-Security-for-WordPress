<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MfaSkip {

	use PluginControllerConsumer;

	public function addMfaSkip( \WP_User $user ) {
		$meta = self::con()->user_metas->for( $user );
		$hashes = \is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [];
		$hashes[ $this->getAgentHash() ] = Services::Request()->ts();

		$maxExpires = self::con()->comps->mfa->getMfaSkip();
		if ( $maxExpires > 0 ) {
			$hashes = \array_filter( $hashes, fn( $ts ) => Services::Request()->ts() - $ts < $maxExpires );
		}

		$meta->hash_loginmfa = $hashes;
	}

	public function canMfaSkip( \WP_User $user ) :bool {
		$canSkip = false;

		$mfaSkip = self::con()->comps->mfa->getMfaSkip();
		if ( $mfaSkip > 0 ) {
			$agentHash = $this->getAgentHash();
			$meta = self::con()->user_metas->for( $user );
			$hashes = \is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [];
			$canSkip = isset( $hashes[ $agentHash ] )
					   && ( (int)$hashes[ $agentHash ] + $mfaSkip ) > Services::Request()->ts();
		}

		return $canSkip;
	}

	private function getAgentHash() :string {
		$hashData = apply_filters( 'shield/2fa_remember_me_params', $this->getDefaultHashParams() );
		return \hash( 'md5', \serialize( empty( $hashData ) ? $this->getDefaultHashParams() : $hashData ) );
	}

	private function getDefaultHashParams() :array {
		return [
			'ip'         => self::con()->this_req->ip,
			'user_agent' => Services::Request()->getUserAgent()
		];
	}
}