<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Firewall extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire Firewall Zone';
	}

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

	public function description() :array {
		return [
			__( 'The Firewall is made up of many different components, and forms the perimeter defense of your WordPress site.', 'wp-simple-firewall' ),
			__( 'What types of attacks your site defends against, and how your site responds to repeated attacks, determines your entire security posture.', 'wp-simple-firewall' ),
			__( 'The purpose of the Firewall is to lock down the site against malicious requests, restrict access to certain resources, and respond appropriately to any request that appears to be malicious.', 'wp-simple-firewall' ),
			__( "The Firewall doesn't stand alone, as it works together with other components such as the Bot & IP blocking system, to form a complete protection system.", 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'fire';
	}

	public function title() :string {
		return __( 'Firewall', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'The Firewall represents the crucial perimeter defense of your WordPress site.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleFirewall::class;
	}
}