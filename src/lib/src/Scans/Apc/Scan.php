<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

class Scan extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->results = \array_filter( \array_map(
			function ( $file ) {
				return $this->getItemScanner()->scan( $file );
			},
			$action->items
		) );
	}

	protected function getItemScanner() :PluginScanner {
		return ( new PluginScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}