<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class SystemPhpVersion extends Base {

	public const SLUG = 'system_php_version';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		return Services::Data()->getPhpVersionIsAtLeast( '7.4' );
	}

	protected function hrefFull() :string {
		return 'https://clk.shldscrty.com/helpshieldminimumrequirements';
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	public function title() :string {
		return __( 'PHP Version', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return sprintf( __( "WordPress is running on a recent version (%s) of PHP (at least %s).", 'wp-simple-firewall' ),
			Services::Data()->getPhpVersionCleaned(), '7.4' );
	}

	public function descUnprotected() :string {
		return \implode( ' ', [
			sprintf( __( "WordPress is running an old version (%s) of PHP.", 'wp-simple-firewall' ),
				Services::Data()->getPhpVersionCleaned() ),
			sprintf( __( "WordPress.org recommends running on at least PHP %s.", 'wp-simple-firewall' ), '7.4' )
		] );
	}
}