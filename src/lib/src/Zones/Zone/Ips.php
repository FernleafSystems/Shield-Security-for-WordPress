<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Ips extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire IP Blocking Zone';
	}

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
			\implode( ' ', [
				__( 'Where the Firewall is your perimeter defense, automatic IP blocking is the core.', 'wp-simple-firewall' ),
			] ),
			sprintf( __( 'By linking together signals from the Firewall, with signals from all other security components, %s builds a visitor profile over time to determine who is human, who is a bot, and who is here for good or bad.', 'wp-simple-firewall' ), $con->labels->Name ),
			\implode( ' ', [
				__( 'Automated attack bots are the largest threat to your WordPress site.', 'wp-simple-firewall' ),
				__( "Bots are powerful because they're practically 100% automated, so the solution against them must also be automated.", 'wp-simple-firewall' ),
				__( "They attack your sites, probe for information, and exploit vulnerabilities at scale, without any hindrance (normally).", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				sprintf( __( "%s's automatic IP blocking will mitigate their attacks by blocking their IPs after your defenses have been triggered.", 'wp-simple-firewall' ), $con->labels->Name ),
				__( "We'll automatically keep your IP block list pruned by removing stale IPs to keep performance highly optimised.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( 'In partnership with CrowdSec, we go a step further and provide crowd-sourced IP blocklists so we know ahead of time which IPs are malicious and block them immediately.', 'wp-simple-firewall' ),
				__( 'Again, we keep the CrowdSec blocklist pruned and optimised to maintain the high performance you demand.', 'wp-simple-firewall' ),
			] ),
			sprintf( __( "silentCAPTCHA is %s's exclusive bot-detection technology that's invisible to your visitors, and removes the need for user interaction with login CAPTCHAs.", 'wp-simple-firewall' ), $con->labels->Name ),
		];
	}

	public function icon() :string {
		return 'robot';
	}

	public function title() :string {
		return __( 'Bots & IPs', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Beat the bots by blocking the IP addresses of repeat offenders.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleIps::class;
	}
}