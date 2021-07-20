<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class BuildHashesForCrowdSource {

	/**
	 * All file keys are their normalised file paths, with the asset root dir stripped from it.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[]
	 */
	public function build( $asset, array $exts ) :array {
		$hashes = [];
		$DM = Services::DataManipulation();
		$dir = wp_normalize_path( $asset->getInstallDir() );
		try {
			if ( empty( $exts ) ) {
				throw new \Exception( 'File extensions are empty' );
			}
			foreach ( StandardDirectoryIterator::create( $dir, 0, [] ) as $file ) {
				/** @var \SplFileInfo $file */
				if ( in_array( strtolower( $file->getExtension() ), $exts ) ) {
					$fullPath = $file->getPathname();
					$key = str_replace( $dir, '', wp_normalize_path( $fullPath ) );
					$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $key ) : strtolower( $key );
					$hashes[ $key ] = hash( 'sha1', $DM->convertLineEndingsDosToLinux( $fullPath ) );
				}
			}
			ksort( $hashes, SORT_NATURAL );
		}
		catch ( \Exception $e ) {
			$hashes = [];
		}
		return $hashes;
	}
}