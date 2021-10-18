<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$tmpResults = $this->getScanController()->getNewResultsSet();

		$copier = new Shield\Scans\Helpers\CopyResultsSets();
		foreach ( $action->items as $file ) {
			$copier->copyTo( $this->scanItem( $file ), $tmpResults );
		}

		$action->results = array_map(
			function ( $item ) {
				return $item->getRawData();
			},
			$tmpResults->getAllItems()
		);
	}

	private function scanItem( string $scanItem ) :ResultsSet {
		/** @var ResultsSet $results */
		$results = $this->getScanController()->getNewResultsSet();

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

		$rawVuls = $lookerUpper->getVulnerabilities( $slug, $version );

		if ( is_array( $rawVuls ) && !empty( $rawVuls[ 'meta' ] ) && $rawVuls[ 'meta' ][ 'total' ] > 0 ) {

			foreach ( array_filter( $rawVuls[ 'vulnerabilities' ] ) as $vul ) {
				$VO = ( new Shield\Scans\Wpv\WpVulnDb\VulnVO() )->applyFromArray( $vul );
				$VO->provider = $rawVuls[ 'meta' ][ 'provider' ];

				/** @var ResultItem $item */
				$item = $this->getScanController()->getNewResultItem();
				$item->slug = $scanItem;
				$item->wpvuln_id = $VO->id;
				$item->wpvuln_vo = $VO->getRawData();
				$results->addItem( $item );
			}
		}

		return $results;
	}
}