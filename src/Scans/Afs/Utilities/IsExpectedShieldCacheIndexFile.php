<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDirHandler;
use FernleafSystems\Wordpress\Services\Services;

class IsExpectedShieldCacheIndexFile {

	public function check( string $path ) :bool {
		$path = wp_normalize_path( $path );
		return $this->isWithinWpContentDir( $path )
			   && \basename( $path ) === 'index.php'
			   && \preg_match( '#^shield-v2-[a-f0-9]{32}$#', \basename( \dirname( $path ) ) ) === 1
			   && $this->hasExpectedContent( $path );
	}

	private function isWithinWpContentDir( string $path ) :bool {
		foreach ( \array_unique( [
			trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ),
			trailingslashit( wp_normalize_path( path_join( ABSPATH, 'wp-content' ) ) ),
		] ) as $contentDir ) {
			if ( \strpos( $path, $contentDir ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	private function hasExpectedContent( string $path ) :bool {
		$FS = Services::WpFs();
		$content = $FS->isAccessibleFile( $path ) ? $FS->getFileContent( $path ) : false;
		return \is_string( $content ) && \hash_equals( CacheDirHandler::CACHE_INDEX_FILE_CONTENT, $content );
	}
}
