<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Logging extends Base {

	public function components() :array {
		return [
			Component\ActivityLogging::class,
			Component\RequestLogging::class,
		];
	}

	public function description() :array {
		return [
			__( 'Logging provides visibility on all WordPress activity and web traffic.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'person-badge-fill';
	}

	public function title() :string {
		return __( 'Logging', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Logging provides visibility on all WordPress activity and web traffic.', 'wp-simple-firewall' );
	}
}