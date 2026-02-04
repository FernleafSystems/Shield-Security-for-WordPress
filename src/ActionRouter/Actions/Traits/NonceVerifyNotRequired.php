<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait NonceVerifyNotRequired {

	protected function isNonceVerifyRequired() :bool {
		return false;
	}
}