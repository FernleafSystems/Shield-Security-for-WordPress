<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate;

use FernleafSystems\Wordpress\Services\Services;

class Zipper {

	private string $baseDir;

	private array $filePaths;

	private string $targetZip;

	public function __construct( string $baseDir, array $filePaths, string $targetZip ) {
		$this->baseDir = $baseDir;
		$this->filePaths = $filePaths;
		$this->targetZip = $targetZip;
	}

	/**
	 * @throws \Exception
	 */
	public function create() {
		try {
			if ( !\class_exists( '\ZipArchive' ) ) {
				throw new \Exception( 'ZipArchive not supported, falling back to PclZip' );
			}
			$this->zipArchive();
		}
		catch ( \Exception $e ) {
			$lib = path_join( ABSPATH, 'wp-admin/includes/class-pclzip.php' );
			if ( \is_file( $lib ) ) {
				require_once( $lib );
			}
			if ( !\class_exists( '\PclZip' ) ) {
				throw new \Exception( sprintf( '"%s" is not available after previous \ZipArchive error "%s".', '\ZipArchive', $e->getMessage() ) );
			}
			$this->pclZip();
		}
	}

	/**
	 * @throws \Exception
	 */
	private function pclZip() :void {
		$this->preCreate();

		$pclZip = new \PclZip( $this->targetZip );

		$actualWpCfg = null;
		if ( !empty( \dirname( $this->baseDir ) ) ) {
			foreach ( $this->filePaths as $idx => $file ) {
				if ( $file === 'wp-config.php' && !\file_exists( path_join( $this->baseDir, $file ) ) ) {
					if ( \file_exists( path_join( \dirname( $this->baseDir ), 'wp-config.php' ) ) ) {
						$actualWpCfg = path_join( \dirname( $this->baseDir ), 'wp-config.php' );
						unset( $this->filePaths[ $idx ] );
						break;
					}
				}
			}
		}

		$full = \array_filter( \array_map( fn( $path ) => path_join( $this->baseDir, $path ), $this->filePaths ), '\is_file' );

		if ( empty( $pclZip->create( $full, PCLZIP_OPT_REMOVE_PATH, trailingslashit( $this->baseDir ) ) ) ) {
			throw new \Exception( 'Failed to create new Zip file with PclZip: '.$pclZip->errorInfo( true ) );
		}

		if ( !empty( $actualWpCfg ) ) {
			$pclZip = new \PclZip( $this->targetZip );
			$pclZip->add( [ $actualWpCfg ], '', \dirname( $actualWpCfg ) );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function zipArchive() :void {
		$this->preCreate();

		$zip = new \ZipArchive();
		if ( !$zip->open( $this->targetZip, \ZIPARCHIVE::CREATE ) ) {
			throw new \Exception( sprintf( 'Failed to create new Zip file: %s', $zip->getStatusString() ) );
		}
		foreach ( $this->filePaths as $path ) {
			$full = path_join( $this->baseDir, $path );
			if ( \is_file( $full ) ) {
				$zip->addFile( $full, \ltrim( $path, '/' ) );
			}
			elseif ( $path === 'wp-config.php' && !empty( \dirname( $this->baseDir ) ) ) {
				$maybeWpCfg = path_join( \dirname( $this->baseDir ), 'wp-config.php' );
				if ( \file_exists( $maybeWpCfg ) ) {
					$zip->addFile( $maybeWpCfg, 'wp-config.php' );
				}
			}
		}
		if ( !$zip->close() ) {
			throw new \Exception( sprintf( 'Failed to write the new ZIP file: %s', $zip->getStatusString() ) );
		}
	}

	private function preCreate() :void {
		if ( Services::WpFs()->isFile( $this->targetZip ) ) {
			Services::WpFs()->deleteFile( $this->targetZip );
		}
	}
}