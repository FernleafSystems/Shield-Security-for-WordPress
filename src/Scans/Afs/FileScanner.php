<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\{
	IsExcludedPhpTranslationFile,
	IsFileContentExcluded
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetFileContext,
	Exceptions\AmbiguousAssetFileException,
	HashVerificationResult
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\{
	AssetTrustState,
	TrustedFileContext
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileScanner {

	use PluginControllerConsumer;
	use ScanActionConsumer;

	/**
	 * @throws \Exception When the file cannot be reliably classified or a required finding record cannot be created.
	 */
	public function scan( string $fullPath ) :?ResultItem {
		$scanCon = self::con()->comps->scans->AFS();
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$item = null;

		$fileExcluded = $this->isFileExcludedFromScans( $fullPath );

		$validFile = false;
		$skipMalwareScan = false;
		$trustedFileContext = null;
		$assetContext = null;
		$assetContextResolved = false;
		$assetOwnershipAmbiguous = false;
		$assetVerification = null;
		$malwareScanClean = false;
		$optimiser = new Processing\FileScanOptimiser();
		$assetTrustState = new AssetTrustState( $action );
		$resolveAssetContext = function () use (
			$fullPath,
			$assetTrustState,
			&$assetContext,
			&$assetContextResolved,
			&$assetOwnershipAmbiguous
		) :?AssetFileContext {
			if ( !$assetContextResolved ) {
				try {
					$assetContext = $assetTrustState->resolveAssetContext( $fullPath );
				}
				catch ( AmbiguousAssetFileException $e ) {
					$assetContext = null;
					$assetOwnershipAmbiguous = true;
				}
				$assetContextResolved = true;
			}
			return $assetContext;
		};
		try {
			if ( $fileExcluded ) {
				$validFile = true;
			}
			if ( !$validFile && $scanCon->isEnabled() && ( new Scans\WpCoreFile( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid() ) {
				$validFile = true;
				$skipMalwareScan = true;
				$trustedFileContext = $this->buildCoreTrustedFileContext( $fullPath );
			}
			if ( !$validFile && $scanCon->isEnabled() && ( new Scans\WpCoreUnrecognisedFile( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid() ) {
				$validFile = true;
			}
			if ( !$validFile && $scanCon->isScanEnabledWpRoot() && ( new Scans\WpRootUnidentified( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid() ) {
				$validFile = true;
			}
			if ( !$validFile && $scanCon->isScanEnabledPlugins() ) {
				$assetContext = $resolveAssetContext();
				if ( $assetOwnershipAmbiguous ) {
					$validFile = true;
				}
				elseif ( $assetContext instanceof AssetFileContext && $assetContext->assetType === 'plugin' ) {
					$pluginScan = ( new Scans\PluginFile( $fullPath ) )
						->setAssetContext( $assetContext )
						->setAssetTrustState( $assetTrustState )
						->setScanActionVO( $action );
					if ( $pluginScan->isFileValid() ) {
						$validFile = true;
						$assetVerification = $pluginScan->getHashVerificationResult();
						if ( $assetVerification instanceof HashVerificationResult ) {
							$skipMalwareScan = $assetVerification->trustedSource;
							if ( $skipMalwareScan ) {
								$trustedFileContext = $assetTrustState->trustedFileContextFromVerification( $assetVerification );
							}
						}
					}
				}
			}
			if ( !$validFile && $scanCon->isScanEnabledThemes() ) {
				$assetContext = $resolveAssetContext();
				if ( $assetOwnershipAmbiguous ) {
					$validFile = true;
				}
				elseif ( $assetContext instanceof AssetFileContext && $assetContext->assetType === 'theme' ) {
					$themeScan = ( new Scans\ThemeFile( $fullPath ) )
						->setAssetContext( $assetContext )
						->setAssetTrustState( $assetTrustState )
						->setScanActionVO( $action );
					if ( $themeScan->isFileValid() ) {
						$validFile = true;
						$assetVerification = $themeScan->getHashVerificationResult();
						if ( $assetVerification instanceof HashVerificationResult ) {
							$skipMalwareScan = $assetVerification->trustedSource;
							if ( $skipMalwareScan ) {
								$trustedFileContext = $assetTrustState->trustedFileContextFromVerification( $assetVerification );
							}
						}
					}
				}
			}
			if ( !$validFile && $scanCon->isScanEnabledWpContent() ) {
				$assetContext = $resolveAssetContext();
				if ( $assetOwnershipAmbiguous || $assetContext instanceof AssetFileContext ) {
					$validFile = true;
				}
				elseif ( ( new Scans\WpContentUnidentified( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid() ) {
					$validFile = true;
				}
			}
		}
		catch ( Exceptions\WpCoreFileMissingException $me ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_missing = true;
		}
		catch ( Exceptions\WpCoreFileChecksumFailException $cfe ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_checksumfail = true;
		}
		catch ( Exceptions\WpCoreFileUnrecognisedException $ufe ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_unrecognised = true;
		}
		catch ( Exceptions\PluginFileUnrecognisedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_plugin = true;
			$item->is_unrecognised = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ];
			$item->comparison_basis = $e->getScanFileData()[ 'comparison_basis' ];
		}
		catch ( Exceptions\PluginFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_plugin = true;
			$item->is_checksumfail = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ];
			$item->comparison_basis = $e->getScanFileData()[ 'comparison_basis' ];
		}
		catch ( Exceptions\ThemeFileUnrecognisedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_unrecognised = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ];
			$item->comparison_basis = $e->getScanFileData()[ 'comparison_basis' ];
		}
		catch ( Exceptions\ThemeFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_checksumfail = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ];
			$item->comparison_basis = $e->getScanFileData()[ 'comparison_basis' ];
		}
		catch ( Exceptions\WpRootFileUnidentifiedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_in_wproot = true;
			$item->is_unidentified = true;
		}
		catch ( Exceptions\WpContentFileUnidentifiedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_in_wpcontent = true;
			$item->is_unidentified = true;
		}
		$canRunMalwareScan = !$fileExcluded
							  && $scanCon->isEnabledMalwareScanPHP()
							  && ( empty( $item ) || !$item->is_missing );
		if ( !$skipMalwareScan && $canRunMalwareScan && empty( $item ) && !( $assetVerification instanceof HashVerificationResult )
			 && ( !$scanCon->isScanEnabledPlugins() || !$scanCon->isScanEnabledThemes() ) ) {
			$assetContext = $resolveAssetContext();
			if ( !$assetOwnershipAmbiguous
				 && $assetContext instanceof AssetFileContext
				 && ( ( $assetContext->assetType === 'plugin' && !$scanCon->isScanEnabledPlugins() )
					  || ( $assetContext->assetType === 'theme' && !$scanCon->isScanEnabledThemes() ) ) ) {
				try {
					$assetVerification = $assetTrustState->verifyAssetContext( $fullPath, $assetContext );
					if ( $assetVerification instanceof HashVerificationResult && $assetVerification->trustedSource ) {
						$skipMalwareScan = true;
						$trustedFileContext = $assetTrustState->trustedFileContextFromVerification( $assetVerification );
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
		if ( !$skipMalwareScan && $canRunMalwareScan && $optimiser->hasCleanMalwareVerdict( $fullPath, $action ) ) {
			$skipMalwareScan = true;
		}

		if ( !$skipMalwareScan && $canRunMalwareScan ) {
			try {
				( new Scans\MalwareFile( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid();
				$malwareScanClean = true;
			}
			catch ( Exceptions\MalwareFileException $mfe ) {
				if ( $item === null ) {
					$item = $this->getResultItem( $fullPath );
					if ( $assetContext instanceof AssetFileContext ) {
						if ( $assetContext->assetType === 'plugin' ) {
							$item->is_in_plugin = true;
							$item->ptg_slug = $assetContext->assetKey;
							$item->asset_version = $assetContext->assetVersion;
						}
						elseif ( $assetContext->assetType === 'theme' ) {
							$item->is_in_theme = true;
							$item->ptg_slug = $assetContext->assetKey;
							$item->asset_version = $assetContext->assetVersion;
						}
					}
				}
				$item->is_mal = true;

				if ( !isset( $mfe->getScanFileData()[ 'mal_sig' ] ) ) {
					throw new \Exception( 'Cannot proceed without a malware signature' );
				}
				$autoFilterMalware = $assetVerification instanceof HashVerificationResult
								 && $assetVerification->verified
								 && $assetVerification->trustedSource;
				$malRecord = ( new Processing\CreateLocalMalwareRecords() )->run(
					$item->path_fragment,
					$mfe->getScanFileData()[ 'mal_sig' ],
					$autoFilterMalware
				);
				$item->malware_record_id = $malRecord->id;
				$item->auto_filter = $autoFilterMalware;
			}
		}

		if ( !empty( $item ) && Services::WpFs()->isAccessibleFile( $fullPath ) ) {
			$item->checksum_sha256 = \hash_file( 'sha256', $fullPath );
		}

		if ( empty( $item ) && $trustedFileContext instanceof TrustedFileContext ) {
			$optimiser->recordKnownValidFile( $fullPath, $trustedFileContext );
		}
		if ( $malwareScanClean ) {
			$optimiser->recordCleanMalwareVerdict( $fullPath, $action );
		}

		return $item;
	}

	private function buildCoreTrustedFileContext( string $fullPath ) :TrustedFileContext {
		return new TrustedFileContext(
			'core',
			'core',
			Services::WpGeneral()->getVersion(),
			Services::WpFs()->getPathRelativeToAbsPath( $fullPath )
		);
	}

	private function getResultItem( string $fullPath ) :ResultItem {
		/** @var ResultItem $item */
		$item = self::con()->comps->scans->AFS()->getNewResultItem();
		$item->path_full = wp_normalize_path( $fullPath );
		$item->path_fragment = Services::WpFs()->getPathRelativeToAbsPath( $item->path_full );
		return $item;
	}

	private function isFileExcludedFromScans( string $fullPath ) :bool {
		return ( new IsFileContentExcluded() )->check( $fullPath ) || ( new IsExcludedPhpTranslationFile() )->check( $fullPath );
	}
}
