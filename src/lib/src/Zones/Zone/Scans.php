<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class Scans extends Base {

	public function components() :array {
		return [
		];
	}

	public function description() :array {
		return [
			__( '.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'shield-shaded';
	}

	public function title() :string {
		return __( 'Scans & Integrity', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protection for users and their sessions.', 'wp-simple-firewall' );
	}
}