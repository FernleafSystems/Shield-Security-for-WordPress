<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

/**
 * Must come after the WP, Plugin and Theme scans.
 */
class WpContentUnidentified extends BaseScan {

	/**
	 * @throws Exceptions\WpContentFileUnidentifiedException
	 */
	protected function runScan() :bool {
		// Is it in the WP root dir?
		if ( $this->inWpContentDir() ) {
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
		$possibles = \array_unique( [
			trailingslashit( wp_normalize_path( path_join( ABSPATH, 'wp-content' ) ) ),
			trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ),
		] );
		$in = false;
		foreach ( $possibles as $possibleRoot ) {
			if ( \strpos( $this->pathFull, $possibleRoot ) === 0 ) {
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
				if ( \strpos( $this->pathFull, $possibleExclusionPath ) === 0 ) {
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