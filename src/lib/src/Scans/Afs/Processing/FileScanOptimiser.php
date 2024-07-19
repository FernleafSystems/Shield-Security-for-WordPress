<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Search\SearchFile;

class FileScanOptimiser {

	use PluginControllerConsumer;

	public function addFiles( array $files ) {
		$FS = Services::WpFs();
		if ( self::con()->cache_dir_handler->exists() && !empty( $files ) ) {
			$pathToHashes = $this->pathToHashes();
			if ( !$FS->exists( $pathToHashes ) ) {
				$FS->touch( $pathToHashes );
			}
			if ( $FS->isAccessibleFile( $pathToHashes ) ) {
				$fileHashes = \array_unique( \array_filter( \array_map(
					function ( $file ) {
						return \hash_file( 'md5', $file );
					},
					\array_filter(
						$files,
						function ( $file ) {
							return Services::WpFs()->isAccessibleFile( (string)$file );
						}
					)
				) ) );

				try {
					$allNotFoundHashes = \array_diff(
						$fileHashes,
						\array_keys( \array_filter( ( new SearchFile( $pathToHashes ) )->multipleExists( $fileHashes ) ) )
					);
					\file_put_contents(
						$pathToHashes,
						\sprintf( '%s:%s', Services::Request()->ts(), \implode( ',', $allNotFoundHashes )."\n" ),
						\FILE_APPEND
					);
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}

	public function filterFilesFromAction( ScanActionVO $action ) {
		if ( \is_array( $action->items ) ) {
			$items = \array_map(
				function ( $item ) {
					return \base64_decode( $item );
				},
				$action->items
			);

			$action->items = \array_map(
				function ( $item ) {
					return \base64_encode( $item );
				},
				\array_diff( $items, $this->findHashedFiles( $items ) )
			);
		}
	}

	public function findHashedFiles( array $files ) :array {
		$FS = Services::WpFs();

		$filesFound = [];

		$pathToHashes = $this->pathToHashes();
		if ( $FS->isAccessibleFile( $pathToHashes ) && $FS->getFileSize( $pathToHashes ) > 0 ) {

			$filesThatExist = \array_filter(
				$files,
				function ( $file ) {
					return Services::WpFs()->isAccessibleFile( (string)$file );
				}
			);

			$filesAndTheirHashes = [];
			foreach ( $filesThatExist as $file ) {
				$filesAndTheirHashes[ $file ] = \hash_file( 'md5', $file );
			}

			try {
				$filesFound = \array_keys( \array_intersect(
					$filesAndTheirHashes,
					\array_keys( \array_filter(
						( new SearchFile( $pathToHashes ) )->multipleExists( \array_values( $filesAndTheirHashes ) )
					) )
				) );
			}
			catch ( \Exception $e ) {
			}
		}

		return $filesFound;
	}

	public function cleanStaleHashesOlderThan( int $ts ) {
		$FS = Services::WpFs();
		$pathToHashes = $this->pathToHashes();
		$pathToHashesTmp = $this->pathToHashes().'.tmp';

		if ( $FS->exists( $pathToHashes ) ) {

			$source = \fopen( $pathToHashes, 'r' );
			$target = \fopen( $pathToHashesTmp, 'w' );

			if ( !\is_resource( $source ) && \is_resource( $target ) ) {
				\fclose( $target );
				$FS->deleteFile( $pathToHashes );
				$FS->deleteFile( $pathToHashesTmp );
			}
			elseif ( !\is_resource( $target ) && \is_resource( $source ) ) {
				\fclose( $source );
				$FS->deleteFile( $pathToHashes );
			}
			else {
				while ( !feof( $source ) ) {
					$line = \fgets( $source );
					if ( \is_string( $line ) ) {
						$colonAt = \strpos( $line, ':' );
						if ( $colonAt !== false ) {
							if ( \substr( $line, 0, $colonAt ) > $ts ) {
								\fputs( $target, $line );
							}
						}
					}
				}
				if ( is_resource( $source ) ) {
					\fclose( $source );
				}
				if ( is_resource( $target ) ) {
					\fclose( $target );
				}

				$FS->move( $pathToHashesTmp, $pathToHashes );
			}
		}
	}

	public function pathToHashes() :string {
		return path_join( self::con()->cache_dir_handler->dir(), 'file_scan_hashes.txt' );
	}
}