<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait SecurityAdminNotRequired {

	public function isSecurityAdminRestricted() :bool {
		return false;
	}
}