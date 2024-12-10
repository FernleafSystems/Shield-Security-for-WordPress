<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class GlobalPluginEnable extends Base {

	public function title() :string {
		return __( 'Global Plugin Enable', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "Set the entire plugin's enabled/disabled status.", 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Globally enable/disable security protections', 'wp-simple-firewall' );
	}
}