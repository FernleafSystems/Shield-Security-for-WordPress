<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait ByPassIpBlock {

	protected function canBypassIpAddressBlock() :bool {
		return true;
	}
}