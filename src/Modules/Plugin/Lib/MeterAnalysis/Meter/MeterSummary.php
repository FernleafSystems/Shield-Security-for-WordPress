<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterSummary extends MeterBase {

	public const SLUG = 'summary';

	public function title() :string {
		return __( 'High-Level System Security Summary', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How good WordPress site & system security is looking', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "This section lets you quickly see how well you're doing, by taking a high-level view on your WordPress & system security.", 'wp-simple-firewall' ),
			__( "Your overall grade incorporates all other security scores as well as some components not directly related to the plugin.", 'wp-simple-firewall' ),
			__( "All sections use a simple grading system from A - F, where A is best, and F is worst.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\AllComponents::class,
			Component\SystemSslCertificate::class,
			Component\SystemPhpVersion::class,
			Component\IpAddressSource::class,
			Component\WpUpdates::class,
			Component\WpDbPassword::class,
			Component\SystemLibOpenssl::class,
		];
	}
}