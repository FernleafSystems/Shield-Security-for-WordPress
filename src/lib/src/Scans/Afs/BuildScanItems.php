<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems {

	use ModConsumer;
	use ScanActionConsumer;

	public function run() :array {
		$this->preBuild();

		$files = array_filter(
			array_unique( array_merge(
				$this->buildFilesFromDisk(),
				$this->buildFilesFromWpHashes()
			) ),
			function ( $path ) {
				return !$this->isWhitelistedPath( $path );
			}
		);

		natsort( $files );

		return array_map(
			function ( $path ) {
				return base64_encode( $path );
			},
			$files
		);
	}

	protected function preBuild() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( empty( $action->scan_root_dirs ) || !is_array( $action->scan_root_dirs ) ) {
			$action->scan_root_dirs = [
				ABSPATH                          => 1,
				path_join( ABSPATH, WPINC )      => 0,
				path_join( ABSPATH, 'wp-admin' ) => 0,
				WP_CONTENT_DIR                   => 0,
			];
		}
		if ( !is_array( $action->paths_whitelisted ) ) {
			/** @var Options $opts */
			$opts = $this->getMod()->getOptions();
			$action->paths_whitelisted = $opts->getWhitelistedPathsAsRegex();
		}
	}

	private function buildFilesFromWpHashes() :array {
		$files = [];

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isReady() ) {
			foreach ( array_keys( $coreHashes->getHashes() ) as $fragment ) {
				// To reduce noise, we exclude plugins and themes (by default)
				if ( strpos( $fragment, 'wp-content/' ) === false ) {
					$files[] = wp_normalize_path( path_join( ABSPATH, $fragment ) );
				}
			}
		}

		return $files;
	}

	private function buildFilesFromDisk() :array {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$files = [];
		foreach ( $action->scan_root_dirs as $scanDir => $depth ) {
			try {
				foreach ( StandardDirectoryIterator::create( $scanDir, (int)$depth, $action->file_exts ) as $item ) {
					/** @var \SplFileInfo $item */
					try {
						if ( !$this->isAutoFilterFile( $item ) ) {
							$files[] = wp_normalize_path( $item->getPathname() );
						}
					}
					catch ( \Exception $e ) {
					}
				}
			}
			catch ( \Exception $e ) {
				error_log(
					sprintf( 'Shield file scanner (%s) attempted to read directory (%s) but there was error: "%s".',
						$action->scan, $scanDir, $e->getMessage() )
				);
			}
		}
		return $files;
	}

	private function isAutoFilterFile( \SplFileInfo $file ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		/**
		 * Remove anything in wp-content as this is only relevant for Plugins/Themes/Malware
		 * and this is PRO-only anyway.
		 */
		return (
				   !$this->getCon()->isPremiumActive()
				   && strpos( wp_normalize_path( $file->getPathname() ), '/wp-content/' ) !== false
			   )
			   ||
			   ( $opts->isAutoFilterResults() && $file->getSize() === 0 );
	}

	private function isWhitelistedPath( string $path ) :bool {
		$whitelisted = false;

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		foreach ( $action->paths_whitelisted as $wlPathRegEx ) {
			if ( preg_match( $wlPathRegEx, $path ) ) {
				$whitelisted = true;
				break;
			}
		}
		return $whitelisted;
	}
}