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
		foreach ( $action->items as $file => $context ) {
			$results = $this->scanItem( $context, $file );
			if ( $results instanceof Shield\Scans\Base\ResultsSet ) {
				$copier->copyTo( $results, $tmpResults );
			}
		}

		$action->results = array_map(
			function ( $item ) {
				return $item->getRawData();
			},
			$tmpResults->getAllItems()
		);
	}

	/**
	 * @param string $context
	 * @param string $file
	 * @return ResultsSet
	 */
	protected function scanItem( $context, $file ) {
		/** @var ResultsSet $results */
		$results = $this->getScanController()->getNewResultsSet();

		$apiToken = $this->getCon()
						 ->getModule_License()
						 ->getWpHashesTokenManager()
						 ->getToken();

		if ( $context == 'plugins' ) {
			$WPP = Services::WpPlugins();
			$slug = $WPP->getSlug( $file );
			if ( empty( $slug ) ) {
				$slug = dirname( $file );
			}
			$version = $WPP->getPluginAsVo( $file )->Version;
			$lookerUpper = new Vulnerabilities\Plugin( $apiToken );
		}
		else {
			$slug = $file;
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
				$item->slug = $file;
				$item->context = $context;
				$item->wpvuln_id = $VO->id;
				$item->wpvuln_vo = $VO->getRawData();

				$results->addItem( $item );
			}
		}

		return $results;
	}
}