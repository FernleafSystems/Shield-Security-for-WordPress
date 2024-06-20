<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	public function components() :array {
		return [];
	}

	public function description() :array {
		return [];
	}

	public function icon() :string {
		return 'grid-1x2-fill';
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'No Subtitle Yet', 'wp-simple-firewall' );
	}
}