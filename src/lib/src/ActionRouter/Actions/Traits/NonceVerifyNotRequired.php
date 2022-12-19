<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait NonceVerifyNotRequired {

	public function isNonceVerifyRequired() :bool {
		return false;
	}
}