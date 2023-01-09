<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class MeterFirewall extends MeterBase {

	public const SLUG = 'firewall';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_Firewall() ];
	}

	public function title() :string {
		return __( 'Powerful WordPress Firewall', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How malicious requests to your site are handled', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "The firewall inspects all data sent in every request to your site.", 'wp-simple-firewall' )
			.' '.__( "If malicious data is detected, the request will be quickly terminated before it can be misused.", 'wp-simple-firewall' ),
			__( "The more rules you employ, the better, but you should always monitor your Activity Log for false positives.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\FirewallDirTraversal::class,
			Component\FirewallWpTerms::class,
			Component\FirewallFieldTruncation::class,
			Component\FirewallPhpCode::class,
			Component\FirewallExeFileUploads::class,
			Component\FirewallAggressive::class,
		];
	}
}