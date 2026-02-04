<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterLockdown extends MeterBase {

	public const SLUG = 'lockdown';

	public function title() :string {
		return __( 'Site Lockdown and Firewall', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How your WordPress site is locked-down and handles malicious requests', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "Is your WordPress site automatically detecting and blocking malicious requests?", 'wp-simple-firewall' ),
			\implode( ' ', [
				__( "There are a few different ways to 'lockdown' a WordPress site.", 'wp-simple-firewall' ),
				__( "It could involve restricting WordPress features such as XML-RPC, or scanning data inside HTTP requests for malicious parameters.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "The firewall inspects all data sent with every request to your site.", 'wp-simple-firewall' ),
				__( "If malicious data is detected, the request will be immediately terminated.", 'wp-simple-firewall' ),
				__( "The more rules you deploy, the better, but you should always monitor your Activity Log for false positives.", 'wp-simple-firewall' ),
			] ),
			__( "There are also options to limit and disable built-in WordPress features such as XML-RPC and anonymous access to the REST API.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\SecurityAdmin::class,
			Component\SecurityAdminAdmins::class,
			Component\SecurityAdminOptions::class,
			Component\LockdownXmlrpc::class,
			Component\LockdownFileEditing::class,
			Component\LockdownAuthorDiscovery::class,
			Component\LockdownAnonymousRestApi::class,
			Component\FirewallDirTraversal::class,
			Component\FirewallSqlQueries::class,
			Component\FirewallFieldTruncation::class,
			Component\FirewallPhpCode::class,
			Component\FirewallAggressive::class,
		];
	}
}