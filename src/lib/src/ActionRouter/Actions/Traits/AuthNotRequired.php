<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait AuthNotRequired {

	public function isUserAuthRequired() :bool {
		return false;
	}
}