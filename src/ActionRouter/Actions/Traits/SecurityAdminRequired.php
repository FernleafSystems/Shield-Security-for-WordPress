<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait SecurityAdminRequired {

	protected function isSecurityAdminRequired() :bool {
		return true;
	}
}