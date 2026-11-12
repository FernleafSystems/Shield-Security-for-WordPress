<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Must come after the WP, Plugin and Theme scans.
 */
class WpContentUnidentified extends BaseScan {

	/**
	 * @throws Exceptions\WpContentFileUnidentifiedException
	 */
	protected function runScan() :bool {
		if ( Services::WpFs()->isAccessibleFile( $this->pathFull ) && $this->inWpContentDir() ) {
			throw new Exceptions\WpContentFileUnidentifiedException( $this->pathFull );
		}
		return false;
	}

	// TODO: empty file extension support
	protected function getSupportedFileExtensions() :array {
		return [
			'ico',
			'js',
			'mo',
			'php',
			'php5',
			'php7',
			'phtm',
		];
	}

	private function inWpContentDir() :bool {
		$FS = Services::WpFs();
		$possibles = \array_unique( [
			path_join( ABSPATH, 'wp-content' ),
			WP_CONTENT_DIR,
		] );
		$in = false;
		foreach ( $possibles as $possibleRoot ) {
			if ( $FS->getPathRelativeToDir( $this->pathFull, $possibleRoot ) !== null ) {
				$in = true;
				break;
			}
		}

		if ( $in ) {
			$possibleExclusionPaths = [];
			foreach ( $possibles as $wpContentPossible ) {
				$possibleExclusionPaths[] = path_join( $wpContentPossible, 'plugins' );
				$possibleExclusionPaths[] = path_join( $wpContentPossible, 'themes' );
				$possibleExclusionPaths[] = path_join( $wpContentPossible, 'mu-plugins' );
			}
			foreach ( $possibleExclusionPaths as $possibleExclusionPath ) {
				if ( $FS->getPathRelativeToDir( $this->pathFull, $possibleExclusionPath ) !== null ) {
					$in = false;
					break;
				}
			}
		}

		return $in;
	}

	protected function getPathExcludes() :array {
		$wpContentPaths = [
			'/advanced-cache.php',
			'/autoptimize_404_handler.php',
			'/breeze-config/breeze-config.php',
			'/uploads/wph/environment.php',
			'/wflogs/rules.php',
			'/wflogs/config-livewaf.php',
		];
		$wpContentPathsRegex = [
			'/uploads/siteground\-optimizer\-assets/siteground\-optimizer\-combined\-js\-[a-zA-Z\d]{32}\.js$',
			'/litespeed/js/(\d+/)?[a-f\d]{32}\.js$',
			'/uploads/.*/backupbuddy_dat\.php$',
			'/.*settings_backup\-.*\.php$',
			'/uploads/.*\.ufm.php$',
			'/jetpack-waf/.*automatic-rules\.php$',
		];

		$excludes = [
			'shield/index.php',
		];
		foreach ( \array_unique( [ \basename( WP_CONTENT_DIR ), 'wp-content' ] ) as $wpContentFragment ) {
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

		return $excludes;
	}
}