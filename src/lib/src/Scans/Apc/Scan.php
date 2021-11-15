<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->results = array_filter( array_map(
			function ( $file ) {
				return $this->getItemScanner()->scan( $file );
			},
			$action->items
		) );
	}

	protected function getItemScanner() :PluginScanner {
		return ( new PluginScanner() )
			->setScanController( $this->getScanController() )
			->setScanActionVO( $this->getScanActionVO() );
	}
}