<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\{
	IsExcludedPhpTranslationFile,
	IsFileContentExcluded
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\TrustedFileContext;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileScanner {

	use PluginControllerConsumer;
	use ScanActionConsumer;

	public function scan( string $fullPath ) :?ResultItem {
		$scanCon = self::con()->comps->scans->AFS();
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$item = null;

		$fileExcluded = $this->isFileExcludedFromScans( $fullPath );

		$validFile = false;
		$skipMalwareScan = false;
		$trustedFileContext = null;
		$malwareScanClean = false;
		$optimiser = new Processing\FileScanOptimiser();
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
				$pluginScan = ( new Scans\PluginFile( $fullPath ) )
					->setScanActionVO( $action );
				if ( $pluginScan->isFileValid() ) {
					$validFile = true;
					$skipMalwareScan = $pluginScan->isVerifiedHashTrustedSource();
					if ( $skipMalwareScan ) {
						$trustedFileContext = $this->buildAssetTrustedFileContext( $pluginScan->getHashVerificationResult() );
					}
				}
			}
			if ( !$validFile && $scanCon->isScanEnabledThemes() ) {
				$themeScan = ( new Scans\ThemeFile( $fullPath ) )
					->setScanActionVO( $action );
				if ( $themeScan->isFileValid() ) {
					$validFile = true;
					$skipMalwareScan = $themeScan->isVerifiedHashTrustedSource();
					if ( $skipMalwareScan ) {
						$trustedFileContext = $this->buildAssetTrustedFileContext( $themeScan->getHashVerificationResult() );
					}
				}
			}
			if ( !$validFile && $scanCon->isScanEnabledWpContent() && ( new Scans\WpContentUnidentified( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid() ) {
				$validFile = true;
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
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ] ?? '';
		}
		catch ( Exceptions\PluginFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_plugin = true;
			$item->is_checksumfail = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ] ?? '';
		}
		catch ( Exceptions\ThemeFileUnrecognisedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_unrecognised = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ] ?? '';
		}
		catch ( Exceptions\ThemeFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_checksumfail = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
			$item->asset_version = $e->getScanFileData()[ 'asset_version' ] ?? '';
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
		catch ( \Exception $e ) {
			//Never reached
		}

		$canRunMalwareScan = !$fileExcluded
							  && $scanCon->isEnabledMalwareScanPHP()
							  && ( empty( $item ) || !$item->is_missing );
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
				$item = $item ?? $this->getResultItem( $fullPath );
				$item->is_mal = true;

				try {
					if ( !isset( $mfe->getScanFileData()[ 'mal_sig' ] ) ) {
						throw new \Exception( 'Cannot proceed without a malware signature' );
					}
					$malRecord = ( new Processing\CreateLocalMalwareRecords() )->run(
						$item->path_fragment,
						$mfe->getScanFileData()[ 'mal_sig' ],
						$validFile
					);
					$item->malware_record_id = $malRecord->id;
					$item->auto_filter = $validFile;
				}
				catch ( \Exception $e ) {
					/** We can't proceed without a linked local Malware Record */
					$item = null;
					error_log( $e->getMessage() );
				}
			}
			catch ( \InvalidArgumentException $e ) {
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
			\str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $fullPath ) )
		);
	}

	private function buildAssetTrustedFileContext( HashVerificationResult $verification ) :TrustedFileContext {
		return new TrustedFileContext(
			$verification->assetType,
			$verification->assetKey,
			$verification->assetVersion,
			$verification->relativePath
		);
	}

	private function getResultItem( string $fullPath ) :ResultItem {
		/** @var ResultItem $item */
		$item = self::con()->comps->scans->AFS()->getNewResultItem();
		$item->path_full = wp_normalize_path( $fullPath );
		$item->path_fragment = \str_replace( wp_normalize_path( ABSPATH ), '', $item->path_full );
		return $item;
	}

	private function isFileExcludedFromScans( string $fullPath ) :bool {
		return ( new IsFileContentExcluded() )->check( $fullPath ) || ( new IsExcludedPhpTranslationFile() )->check( $fullPath );
	}
}
