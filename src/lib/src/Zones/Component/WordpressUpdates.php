<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class WordpressUpdates extends Base {

	public function title() :string {
		return __( 'WordPress Updates', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'A high-level overview of your WordPress updates status.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}

	protected function hasConfigAction() :bool {
		return false;
	}
}