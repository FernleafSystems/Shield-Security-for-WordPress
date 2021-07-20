<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility\VerifyFileByHash;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Utilities\Code\AssessPhpFile;

/**
 * Class BaseScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files
 */
abstract class BaseScanFromFileMap {

	use ModConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$action = $this->getScanActionVO();
		$results = $action->getNewResultsSet();

		$isAutoFilter = $opts->isAutoFilterResults();

		if ( is_array( $action->items ) ) {
			$hashVerifier = ( new VerifyFileByHash() )->setMod( $this->getMod() );
			foreach ( $action->items as $key => $fullPath ) {

				if ( !$isAutoFilter || !$this->isEmptyOfCode( $fullPath ) ) {

					if ( !$hashVerifier->verify( $fullPath ) ) {
						$item = $this->getFileScanner()->scan( $fullPath );
						// We can exclude files that are empty of relevant code
						if ( $item instanceof Scans\Base\ResultItem ) {
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