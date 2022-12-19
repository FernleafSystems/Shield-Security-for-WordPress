<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait NonceVerifyRequired {

	public function isNonceVerifyRequired() :bool {
		return true;
	}
}