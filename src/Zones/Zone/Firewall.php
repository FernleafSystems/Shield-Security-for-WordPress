<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Firewall extends Base {

	public function components() :array {
		return [
			Component\WebApplicationFirewall::class,
			Component\UsernameFishingBlock::class,
			Component\XmlRpcDisable::class,
			Component\RateLimiting::class,
			Component\AnonRestApiDisable::class,
			Component\FileEditingBlock::class,
		];
	}

	public function title() :string {
		return __( 'Firewall', 'wp-simple-firewall' );
	}
}
