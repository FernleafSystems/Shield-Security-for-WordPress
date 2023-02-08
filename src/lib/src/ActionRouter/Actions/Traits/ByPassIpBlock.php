<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait ByPassIpBlock {

	public function canBypassIpAddressBlock() :bool {
		return true;
	}
}