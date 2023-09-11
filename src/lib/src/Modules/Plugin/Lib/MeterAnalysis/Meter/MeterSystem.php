<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterSystem extends MeterBase {

	public const SLUG = 'system';

	public function title() :string {
		return __( 'WordPress Hosting & System Configuration', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How Well The WordPress Hosting System Is Configured', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "This section assesses critical components of your WordPress hosting system and its configuration.", 'wp-simple-firewall' ),
			__( "While these items aren't all directly related to security, they play a role in your site performance and preparedness for future developments.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\SystemSslCertificate::class,
			Component\SystemPhpVersion::class,
			Component\WpUpdates::class,
			Component\WpDbPassword::class,
			Component\SystemLibOpenssl::class,
		];
	}
}