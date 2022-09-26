<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;

trait SecurityAdminRequired {

	public function isSecurityAdminRestricted() :bool {
		return true;
	}
}