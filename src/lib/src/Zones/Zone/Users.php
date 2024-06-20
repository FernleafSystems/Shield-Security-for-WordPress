<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Users extends Base {

	public function components() :array {
		return [
			Component\PwnedPasswords::class,
		];
	}

	public function description() :array {
		return [
			__( 'Protection for user accounts.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'person-badge-fill';
	}

	public function title() :string {
		return __( 'Users', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protection for user accounts.', 'wp-simple-firewall' );
	}
}