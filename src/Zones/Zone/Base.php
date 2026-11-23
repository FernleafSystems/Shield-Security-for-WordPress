<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	public function components() :array {
		return [];
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}
}
