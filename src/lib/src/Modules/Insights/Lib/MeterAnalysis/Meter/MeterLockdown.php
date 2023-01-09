<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class MeterLockdown extends MeterBase {

	public const SLUG = 'lockdown';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_Lockdown() ];
	}

	public function title() :string {
		return __( 'Site Lockdown', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How various WordPress components are locked-down', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "This section assesses how, and whether, you've locked down certain WordPress components which, for the vast majority of website, don't need to remain enabled by default.", 'wp-simple-firewall' ),
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
		];
	}
}