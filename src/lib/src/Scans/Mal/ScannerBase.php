<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class ScannerBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
abstract class ScannerBase {

	use ModConsumer;

	/**
	 * @var string[]
	 */
	private $aMalSigsRegex;

	/**
	 * @var string[]
	 */
	private $aMalSigsSimple;

	/**
	 * @return ResultsSet
	 */
	abstract public function run();

	/**
	 * @param string $sFullPath
	 * @return ResultItem|null
	 */
	protected function scanPath( $sFullPath ) {
		$oResultItem = null;

		try {
			$oLocator = ( new File\LocateStrInFile() )->setPath( $sFullPath );
		}
		catch ( \Exception $oE ) {
			return $oResultItem;
		}

		{ // Simple Patterns first
			$oLocator->setIsRegEx( false );
			foreach ( $this->getMalSigsSimple() as $sSig ) {

				$aLines = $oLocator->setNeedle( $sSig )
								   ->run();
				if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
					$oResultItem = $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
					return $oResultItem;
				}
			}
		}

		{ // RegEx Patterns
			$oLocator->setIsRegEx( true );
			foreach ( $this->getMalSigsRegex() as $sSig ) {

				$aLines = $oLocator->setNeedle( $sSig )
								   ->run();
				if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
					$oResultItem = $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
					return $oResultItem;
				}
			}
		}

		return $oResultItem;
	}

	/**
	 * @param $aLines
	 * @param $sFullPath
	 * @param $sSig
	 * @return ResultItem
	 */
	private function getResultItemFromLines( $aLines, $sFullPath, $sSig ) {
		$oResultItem = new ResultItem();
		$oResultItem->path_full = wp_normalize_path( $sFullPath );
		$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
		$oResultItem->is_mal = true;
		$oResultItem->mal_sig = base64_encode( $sSig );
		$oResultItem->file_lines = $aLines;
		return $oResultItem;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function canExcludeFile( $sFullPath ) {
		return $this->isValidCoreFile( $sFullPath ) || $this->isPluginFileValid( $sFullPath );
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isPluginFileValid( $sFullPath ) {
		$bCanExclude = false;

		if ( strpos( $sFullPath, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0 ) {

			$oPluginFiles = new WpOrg\Plugin\Files();
			$oThePlugin = $oPluginFiles->findPluginFromFile( $sFullPath );
			if ( $oThePlugin instanceof WpPluginVo ) {
				try {
					$sTmpFile = $oPluginFiles
						->setWorkingSlug( $oThePlugin->slug )
						->setWorkingVersion( $oThePlugin->Version )
						->getOriginalFileFromVcs( $sFullPath );
					if ( Services::WpFs()->exists( $sTmpFile )
						 && ( new File\Compare\CompareHash() )->isEqualFilesMd5( $sTmpFile, $sFullPath ) ) {
						$bCanExclude = true;
					}
				}
				catch ( \Exception $oE ) {
				}
			}
		}

		return $bCanExclude;
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	private function isValidCoreFile( $sFullPath ) {
		$sCoreHash = Services::CoreFileHashes()->getFileHash( $sFullPath );
		try {
			$bValid = !empty( $sCoreHash )
					  && ( new File\Compare\CompareHash() )->isEqualFileMd5( $sFullPath, $sCoreHash );
		}
		catch ( \Exception $oE ) {
			$bValid = false;
		}
		return $bValid;
	}

	/**
	 * @return string[]
	 */
	public function getMalSigsRegex() {
		return $this->aMalSigsRegex;
	}

	/**
	 * @return string[]
	 */
	public function getMalSigsSimple() {
		return $this->aMalSigsSimple;
	}

	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setMalSigsRegex( $aSigs ) {
		$this->aMalSigsRegex = $aSigs;
		return $this;
	}

	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setMalSigsSimple( $aSigs ) {
		$this->aMalSigsSimple = $aSigs;
		return $this;
	}
}