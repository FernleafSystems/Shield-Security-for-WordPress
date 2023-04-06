<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\IsFileExcluded;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

/**
 * Must come after the WP, Plugin and Theme scans.
 */
class WpContentUnidentified extends BaseScan {

	/**
	 * @throws Exceptions\WpContentFileUnidentifiedException
	 */
	public function scan() :bool {
		// Is it in the WP root dir?
		if ( $this->inWpContentDir() && $this->isExtensionIncluded() && !$this->isExcluded() ) {
			throw new Exceptions\WpContentFileUnidentifiedException( $this->pathFull );
		}
		return false;
	}

	private function inWpContentDir() :bool {
		$possibles = array_unique( [
			trailingslashit( wp_normalize_path( path_join( ABSPATH, 'wp-content' ) ) ),
			trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ),
		] );
		$in = false;
		foreach ( $possibles as $possibleRoot ) {
			if ( strpos( $this->pathFull, $possibleRoot ) === 0 ) {
				$in = true;
				break;
			}
		}

		if ( $in ) {
			$possibleExclusionPaths = [];
			foreach ( $possibles as $wpContentPossible ) {
				$possibleExclusionPaths[] = trailingslashit( path_join( $wpContentPossible, 'plugins' ) );
				$possibleExclusionPaths[] = trailingslashit( path_join( $wpContentPossible, 'themes' ) );
				$possibleExclusionPaths[] = trailingslashit( path_join( $wpContentPossible, 'mu-plugins' ) );
			}
			foreach ( $possibleExclusionPaths as $possibleExclusionPath ) {
				if ( strpos( $this->pathFull, $possibleExclusionPath ) === 0 ) {
					$in = false;
					break;
				}
			}
		}

		return $in;
	}

	private function isExtensionIncluded() :bool {
		$ext = Paths::Ext( $this->pathFull );
		return empty( $ext ) ||
			   preg_match( sprintf( '#^(%s)$#i', implode( '|', [
				   'ico',
				   'php',
				   'phtm',
				   'js',
			   ] ) ), $ext );
	}

	private function isExcluded() :bool {

		$wpContentPaths = [
			'/advanced-cache.php',
			'/autoptimize_404_handler.php',
			'/breeze-config/breeze-config.php',
			'/uploads/wph/environment.php',
		];
		$wpContentPathsRegex = [
			'/uploads/siteground\-optimizer\-assets/siteground\-optimizer\-combined\-js\-[a-zA-Z\d]{32}\.js$',
		];

		$excludes = [
			'shield/index.php',
		];
		foreach ( array_unique( [ basename( WP_CONTENT_DIR ), 'wp-content' ] ) as $wpContentFragment ) {
			foreach ( $wpContentPaths as $wpContentPath ) {
				$excludes[] = $wpContentFragment.$wpContentPath;
			}
			foreach ( $wpContentPathsRegex as $wpContentPathRegex ) {
				$excludes[] = sprintf( '#%s%s#i',
					preg_quote( wp_normalize_path( path_join( ABSPATH, $wpContentFragment ) ), '#' ),
					$wpContentPathRegex
				);
			}
		}

		return ( new IsFileExcluded() )->check( $this->pathFull, $excludes );
	}
}