<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions\AssetHashesNotFound,
	Exceptions\NoneAssetFileException,
	Exceptions\UnrecognisedAssetFile,
	Query
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files;

class PluginFile extends BasePluginThemeFile {

	/**
	 * @throws Exceptions\PluginFileUnrecognisedException
	 * @throws Exceptions\PluginFileChecksumFailException
	 */
	public function scan() :bool {
		$valid = false;

		$asset = ( new Files() )->findPluginFromFile( $this->pathFull );
		if ( !empty( $asset ) ) {

			if ( $this->isSupportedFileExt() ) {
				try {
					if ( !( new Query() )->verifyHash( $this->pathFull ) ) {
						throw new Exceptions\PluginFileChecksumFailException( $this->pathFull );
					}
					$valid = true;
				}
				catch ( AssetHashesNotFound $e ) {
					// We don't have any hashes to use
				}
				catch ( NoneAssetFileException $e ) {
					// Never reached since we've already located the asset
				}
				catch ( UnrecognisedAssetFile $e ) {
					throw new Exceptions\PluginFileUnrecognisedException( $this->pathFull );
				}
				catch ( \InvalidArgumentException $e ) {
				}
			}
			else {
				$valid = true;
			}
		}

		return $valid;
	}
}