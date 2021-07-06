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
	public function build( $asset ) :array {
		$hashes = [];
		$DM = Services::DataManipulation();
		$dir = wp_normalize_path( $asset->getInstallDir() );
		try {
			$exts = $this->getExtensions();
			foreach ( StandardDirectoryIterator::create( $dir, 0, [] ) as $file ) {
				/** @var \SplFileInfo $file */
				if ( in_array( strtolower( $file->getExtension() ), $exts ) ) {
					$fullPath = $file->getPathname();
					$key = str_replace( $dir, '', wp_normalize_path( $fullPath ) );
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

	private function getExtensions() :array {
		return [
			'php',
			'php5',
			'php7',
			'js',
			'json',
			'css',
			'htm',
			'html',
			'svg',
			'twig',
			'hbs',
		];
	}
}