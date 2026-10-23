<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\AssetFileRules;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;

class BuildHashesForCrowdSource {

	/**
	 * Git's binary test: a NUL byte in this many leading bytes marks a binary file. The API classifies
	 * files with exactly the same rule, so submitted collections match its WordPress.org hashes.
	 */
	private const BINARY_PROBE_BYTES = 8000;

	/**
	 * All file keys are their normalised file paths, with the asset root dir stripped from it.
	 * Text files are hashed with LF line endings; binary files are hashed byte for byte. A file covered by an
	 * AssetFileRules rule is hashed as the API publishes it, so collections from different downloads agree.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[]
	 */
	public function build( $asset, array $exts ) :array {
		$hashes = [];
		$converter = new ConvertLineEndings();
		$rules = new AssetFileRules();
		$dir = wp_normalize_path( $asset->getInstallDir() );
		try {
			if ( empty( $exts ) ) {
				throw new \Exception( __( 'File extensions are empty.', 'wp-simple-firewall' ) );
			}
			foreach ( StandardDirectoryIterator::create( $dir ) as $file ) {
				/** @var \SplFileInfo $file */
				if ( \in_array( \strtolower( $file->getExtension() ), $exts ) ) {
					$fullPath = $file->getPathname();
					$key = \strtolower( \str_replace( $dir, '', wp_normalize_path( $fullPath ) ) );
					$content = (string)Services::WpFs()->getFileContent( $fullPath );
					$isBinary = \str_contains( \substr( $content, 0, self::BINARY_PROBE_BYTES ), "\0" );
					$normalised = $isBinary ? $content : $converter->dosToLinux( $content );
					$hashes[ $key ] = \hash( 'sha1', $rules->apply( $key, $normalised ) ?? $normalised );
				}
			}
			\ksort( $hashes, \SORT_NATURAL );
		}
		catch ( \Exception $e ) {
			$hashes = [];
		}
		return $hashes;
	}
}
