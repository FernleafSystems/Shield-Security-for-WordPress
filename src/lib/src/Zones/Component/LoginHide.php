<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class LoginHide extends Base {

	public function title() :string {
		return __( 'Hide WP Login', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Hide The WP Login Page.', 'wp-simple-firewall' );
	}
}