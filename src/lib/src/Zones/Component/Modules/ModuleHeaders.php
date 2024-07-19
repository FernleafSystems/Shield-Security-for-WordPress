<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules;

class ModuleHeaders extends ModuleBase {

	public function title() :string {
		return __( 'HTTP Headers', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'HTTP Headers', 'wp-simple-firewall' );
	}
}