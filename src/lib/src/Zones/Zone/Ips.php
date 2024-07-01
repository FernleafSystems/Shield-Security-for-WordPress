<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Ips extends Base {

	public function components() :array {
		return [
			Component\AutoIpBlocking::class,
			Component\CrowdsecBlocking::class,
			Component\SilentCaptcha::class,
			Component\BotActions::class,
		];
	}

	public function description() :array {
		$con = self::con();
		return [
			__( 'Automated attack bots are the largest threat to your WordPress site.', 'wp-simple-firewall' ),
			\implode( ' ', [
				__( "Bots are powerful because they're practically 100% automated, so the solution against them must also be automatic.", 'wp-simple-firewall' ),
				__( "They attack your sites, probe for information, and exploit vulnerabilities at scale, without any hindrance.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				sprintf( __( "%s's automatic IP blocking will mitigate their attacks by blocking their IPs after your defenses have been triggered.", 'wp-simple-firewall' ), $con->getHumanName() ),
				__( "We'll automatically keep your IP block list pruned by removing stale IPs to keep performance highly optimised.", 'wp-simple-firewall' ),
				__( 'In partnership with CrowdSec, we also provide crowd-sourced IP blocklists so we know ahead of time which IPs are most likely to be malicious.', 'wp-simple-firewall' ),
			] ),
			sprintf( __( "silentCAPTCHA is %s's exclusive bot-detection technology that's invisible to your visitors, and removes the need for user interaction with login CAPTCHAs.", 'wp-simple-firewall' ), $con->getHumanName() ),
		];
	}

	public function icon() :string {
		return 'robot';
	}

	public function title() :string {
		return __( 'Bots & IPs', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'The Firewall represents the core foundation to your WordPress security & protection.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\ModuleIps::class;
	}
}