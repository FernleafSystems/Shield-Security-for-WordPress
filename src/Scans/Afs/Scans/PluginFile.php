<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions\AssetHashesNotFound,
	Exceptions\NonAssetFileException,
	Exceptions\UnrecognisedAssetFile,
	Query
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files;

class PluginFile extends BasePluginThemeFile {

	protected function canScan() :bool {
		$can = parent::canScan();
		if ( $can ) {
			$this->asset = ( new Files() )->findPluginFromFile( $this->pathFull );
			$can = !empty( $this->asset );
		}
		return $can;
	}

	/**
	 * @throws Exceptions\PluginFileUnrecognisedException
	 * @throws Exceptions\PluginFileChecksumFailException
	 */
	protected function runScan() :bool {
		try {
			$verification = ( new Query() )->verifyHashWithSource( $this->pathFull );
			if ( !$verification->verified ) {
				throw new Exceptions\PluginFileChecksumFailException( $this->pathFull, [
					'slug'          => $this->asset->unique_id,
					'asset_version' => (string)$this->asset->Version,
				] );
			}
			$this->hashVerificationResult = $verification;
			$this->verifiedHashTrustedSource = $verification->trustedSource;
			$valid = true;
		}
		catch ( UnrecognisedAssetFile $e ) {
			throw new Exceptions\PluginFileUnrecognisedException( $this->pathFull, [
				'slug'          => $this->asset->unique_id,
				'asset_version' => (string)$this->asset->Version,
			] );
		}
		catch ( \InvalidArgumentException|AssetHashesNotFound|NonAssetFileException $e ) {
			$valid = false;
		}

		return $valid;
	}
}
