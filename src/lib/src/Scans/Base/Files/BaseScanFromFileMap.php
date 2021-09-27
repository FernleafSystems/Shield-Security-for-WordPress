<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility\VerifyFileByHash;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Utilities\Code\AssessPhpFile;

abstract class BaseScanFromFileMap {

	use ScanControllerConsumer;
	use ModConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$action = $this->getScanActionVO();
		$results = $this->getScanController()->getNewResultsSet();

		$isAutoFilter = $opts->isAutoFilterResults();

		if ( is_array( $action->items ) ) {
			$hashVerifier = ( new VerifyFileByHash() )->setMod( $this->getMod() );
			foreach ( $action->items as $key => $fullPath ) {

				if ( !$isAutoFilter || !$this->isEmptyOfCode( $fullPath ) ) {

					if ( !$hashVerifier->verify( $fullPath ) ) {
						$item = $this->getFileScanner()
									 ->setScanController( $this->getScanController() )
									 ->setMod( $this->getMod() )
									 ->setScanActionVO( $action )
									 ->scan( $fullPath );
						// We can exclude files that are empty of relevant code
						if ( !empty( $item ) ) {
							$results->addItem( $item );
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * @return BaseFileScanner
	 */
	abstract protected function getFileScanner();

	protected function isEmptyOfCode( string $path ) :bool {
		try {
			if ( strpos( $path, wp_normalize_path( ABSPATH ) ) === false ) {
				$path = path_join( ABSPATH, $path );
			}
			$isEmpty = ( new AssessPhpFile() )->isEmptyOfCode( $path );
		}
		catch ( \Exception $e ) {
			$isEmpty = false;
		}
		return $isEmpty;
	}
}