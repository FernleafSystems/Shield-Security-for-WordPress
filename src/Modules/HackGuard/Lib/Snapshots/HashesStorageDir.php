<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class HashesStorageDir {

	use PluginControllerConsumer;

	public const ACTIVE_MARKER = 'ptguard-active.txt';

	public const SUFFIX_LENGTH = 16;

	private static ?string $dir = null;

	private static ?string $rootDir = null;

	public function getTempDir( bool $create = true ) :string {
		$rootDir = untrailingslashit( wp_normalize_path( self::con()->cache_dir_handler->dir() ) );
		if ( empty( $rootDir ) ) {
			self::$dir = null;
			self::$rootDir = null;
			return '';
		}

		if ( !empty( self::$dir )
			 && self::$rootDir === $rootDir
			 && $this->isUsableHashDir( self::$dir, $rootDir )
		) {
			return self::$dir;
		}

		$dir = $this->locateTempDir( $rootDir );
		if ( empty( $dir ) && $create ) {
			$dir = self::con()->cache_dir_handler->buildSubDir( 'ptguard-'.wp_generate_password( self::SUFFIX_LENGTH, false ) );
		}

		$dir = untrailingslashit( wp_normalize_path( (string)$dir ) );
		if ( $this->isUsableHashDir( $dir, $rootDir ) ) {
			$this->writeActiveMarker( $rootDir, \basename( $dir ) );
			self::$dir = $dir;
			self::$rootDir = $rootDir;
		}
		else {
			self::$dir = null;
			self::$rootDir = null;
			$dir = '';
		}

		return self::$dir ?? '';
	}

	private function locateTempDir( string $rootDir ) :string {
		$dir = $this->locateMarkedDir( $rootDir );
		return empty( $dir ) ? $this->locateNewestHashDir( $rootDir ) : $dir;
	}

	private function locateMarkedDir( string $rootDir ) :string {
		$dir = '';
		$FS = Services::WpFs();
		$marker = path_join( $rootDir, self::ACTIVE_MARKER );
		if ( $FS->isAccessibleFile( $marker ) ) {
			$activeDirBasename = \trim( (string)$FS->getFileContent( $marker ) );
			$activeDir = path_join( $rootDir, $activeDirBasename );
			if ( $this->isHashDirBasename( $activeDirBasename ) && $this->isUsableHashDir( $activeDir, $rootDir ) ) {
				$dir = untrailingslashit( wp_normalize_path( $activeDir ) );
			}
		}
		return $dir;
	}

	private function locateNewestHashDir( string $rootDir ) :string {
		$FS = Services::WpFs();
		$hashDirs = [];
		foreach ( $FS->getAllFilesInDir( $rootDir ) as $fileItem ) {
			if ( $FS->isDir( $fileItem ) && $this->isHashDirBasename( \basename( $fileItem ) ) ) {
				$hashDirs[] = [
					'dir'   => untrailingslashit( wp_normalize_path( $fileItem ) ),
					'mtime' => $FS->getModifiedTime( $fileItem ),
				];
			}
		}

		\usort( $hashDirs, static function ( array $a, array $b ) :int {
			return $b[ 'mtime' ] <=> $a[ 'mtime' ] ?: \strcmp( $a[ 'dir' ], $b[ 'dir' ] );
		} );

		return (string)( $hashDirs[ 0 ][ 'dir' ] ?? '' );
	}

	private function isUsableHashDir( string $dir, string $rootDir ) :bool {
		$dir = untrailingslashit( wp_normalize_path( $dir ) );
		$rootDir = untrailingslashit( wp_normalize_path( $rootDir ) );
		return !empty( $dir )
			   && $this->isHashDirBasename( \basename( $dir ) )
			   && \str_starts_with( $dir.'/', $rootDir.'/' )
			   && Services::WpFs()->isDir( $dir );
	}

	private function isHashDirBasename( string $basename ) :bool {
		return \preg_match( sprintf( '#^ptguard-[a-z0-9]{%s}$#i', self::SUFFIX_LENGTH ), $basename ) === 1;
	}

	private function writeActiveMarker( string $rootDir, string $hashDirBasename ) :void {
		if ( $this->isHashDirBasename( $hashDirBasename ) ) {
			Services::WpFs()->putFileContent( path_join( $rootDir, self::ACTIVE_MARKER ), $hashDirBasename );
		}
	}
}
