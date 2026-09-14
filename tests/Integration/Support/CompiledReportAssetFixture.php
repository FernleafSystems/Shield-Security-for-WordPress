<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CompiledReportAssetReadiness;

class CompiledReportAssetFixture {

	private static ?CompiledReportAssetReadiness $readiness = null;

	public static function ensureReady( string $pluginRoot ) :void {
		if ( self::$readiness === null ) {
			self::$readiness = new CompiledReportAssetReadiness();
		}

		self::$readiness->ensureReady( $pluginRoot );
	}
}
