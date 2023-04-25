<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions\AssetHashesNotFound,
	Exceptions\NonAssetFileException,
	Exceptions\UnrecognisedAssetFile,
	Query
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\Files;

class ThemeFile extends BasePluginThemeFile {

	protected function canScan() :bool {
		$can = parent::canScan();
		if ( $can ) {
			$this->asset = ( new Files() )->findThemeFromFile( $this->pathFull );
			$can = !empty( $this->asset );
		}
		return $can;
	}

	/**
	 * @throws Exceptions\ThemeFileUnrecognisedException
	 * @throws Exceptions\ThemeFileChecksumFailException
	 */
	protected function runScan() :bool {
		try {
			if ( !( new Query() )->verifyHash( $this->pathFull ) ) {
				throw new Exceptions\ThemeFileChecksumFailException( $this->pathFull, [
					'slug' => $this->asset->unique_id,
				] );
			}
			$valid = true;
		}
		catch ( UnrecognisedAssetFile $e ) {
			throw new Exceptions\ThemeFileUnrecognisedException( $this->pathFull, [
				'slug' => $this->asset->unique_id,
			] );
		}
		catch ( \InvalidArgumentException|AssetHashesNotFound|NonAssetFileException $e ) {
			$valid = false;
		}

		return $valid;
	}
}