<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Firewall extends Base {

	public function components() :array {
		return [
			Component\RateLimiting::class,
			Component\XmlRpcDisable::class,
			Component\AnonRestApiDisable::class,
		];
	}

	public function description() :array {
		return [
			__( 'Firewall forms the core of your WordPress defense.', 'wp-simple-firewall' ),
			__( 'How well your site performs against attacks, what types of attacks are defended, and how you respond to repeated attacks, dictates your entire security posture.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'fire';
	}

	public function title() :string {
		return __( 'Firewall', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'The Firewall represents the core foundation to your WordPress security & protection.', 'wp-simple-firewall' );
	}
}