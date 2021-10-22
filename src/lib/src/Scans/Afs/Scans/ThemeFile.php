<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions\AssetHashesNotFound,
	Exceptions\NoneAssetFileException,
	Exceptions\UnrecognisedAssetFile,
	Query
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\Files;

class ThemeFile extends BasePluginThemeFile {

	/**
	 * @throws Exceptions\ThemeFileUnrecognisedException
	 * @throws Exceptions\ThemeFileChecksumFailException
	 */
	public function scan() :bool {
		$valid = false;

		$asset = ( new Files() )->findThemeFromFile( $this->pathFull );
		if ( !empty( $asset ) ) {

			if ( $this->isSupportedFileExt() ) {
				try {
					$verifiedHash = ( new Query() )
						->setMod( $this->getMod() )
						->verifyHash( $this->pathFull );
					if ( !$verifiedHash ) {
						throw new Exceptions\ThemeFileChecksumFailException( $this->pathFull );
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
					throw new Exceptions\ThemeFileUnrecognisedException( $this->pathFull );
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