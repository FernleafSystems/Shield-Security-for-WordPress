<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Headers extends Base {

	public function components() :array {
		return [
			Component\HeadersGeneral::class,
			Component\HeadersCsp::class,
		];
	}

	public function title() :string {
		return __( 'HTTP Headers', 'wp-simple-firewall' );
	}
}
