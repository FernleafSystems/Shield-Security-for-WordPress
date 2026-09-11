<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

class Scan extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$results = [];
		foreach ( $action->items as $file ) {
			$action->tickProgress();
			$result = $this->getItemScanner()->scan( $file );
			if ( !empty( $result ) ) {
				$results[] = $result;
			}
		}
		$action->results = $results;
	}

	protected function getItemScanner() :PluginScanner {
		return ( new PluginScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}
