<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class PluginGeneral extends Base {

	public function title() :string {
		return __( 'General Plugin Configuration', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Configure non-security related options of the plugin.', 'wp-simple-firewall' );
	}
}