<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$results = [];
		foreach ( $action->items as $file ) {
			$results[] = $this->scanItem( $file );
		}

		$action->results = array_filter( $results );
	}

	private function scanItem( string $scanItem ) :array {
		$apiToken = $this->getCon()
						 ->getModule_License()
						 ->getWpHashesTokenManager()
						 ->getToken();

		if ( strpos( $scanItem, '/' ) ) { // plugin file
			$WPP = Services::WpPlugins();
			$slug = $WPP->getSlug( $scanItem );
			if ( empty( $slug ) ) {
				$slug = dirname( $scanItem );
			}
			$version = $WPP->getPluginAsVo( $scanItem )->Version;
			$lookerUpper = new Vulnerabilities\Plugin( $apiToken );
		}
		else { // theme dir
			$slug = $scanItem;
			$version = Services::WpThemes()->getTheme( $slug )->get( 'Version' );
			$lookerUpper = new Vulnerabilities\Theme( $apiToken );
		}

		$result = [];

		$rawVuls = $lookerUpper->getVulnerabilities( $slug, $version );
		if ( is_array( $rawVuls ) && !empty( $rawVuls[ 'meta' ] ) && $rawVuls[ 'meta' ][ 'total' ] > 0 ) {
			$result[ 'slug' ] = $scanItem;
			$result[ 'is_vulnerable' ] = true;
			$result[ 'vulnerability_total' ] = $rawVuls[ 'meta' ][ 'total' ];
		}

		return $result;
	}
}