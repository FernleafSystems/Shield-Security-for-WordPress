<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterLockdown extends MeterBase {

	public const SLUG = 'lockdown';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_Lockdown() ];
	}

	public function title() :string {
		return __( 'Site Lockdown and Firewall', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How your WordPress site is locked-down and handles malicious requests', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "This section assesses how well you've locked down WordPress features.", 'wp-simple-firewall' )
			.' '.__( "For the vast majority of websites, certain features don't to be enabled.", 'wp-simple-firewall' ),
			sprintf( __( "This section also includes the WordPress firewall and highlights how %s handles certain types of requests.", 'wp-simple-firewall' ),
				$this->getCon()->getHumanName() ),
			__( "The firewall inspects all data sent with every request to your site.", 'wp-simple-firewall' )
			.' '.__( "If malicious data is detected, the request will be immediately terminated.", 'wp-simple-firewall' ),
			__( "The more rules you deploy, the better, but you should always monitor your Activity Log for false positives.", 'wp-simple-firewall' ),
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
			Component\FirewallWpTerms::class,
			Component\FirewallFieldTruncation::class,
			Component\FirewallPhpCode::class,
			Component\FirewallExeFileUploads::class,
			Component\FirewallAggressive::class,
		];
	}
}