<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class HeadersGeneral extends Base {

	public function title() :string {
		return __( 'Basic HTTP Headers', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Configure some basic HTTP Headers to protect your visitors and site content.', 'wp-simple-firewall' );
	}
}