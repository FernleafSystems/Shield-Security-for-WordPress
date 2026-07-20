<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions\AssetHashesNotFound,
	Exceptions\NonAssetFileException,
	Exceptions\UnrecognisedAssetFile
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

class PluginFile extends BasePluginThemeFile {

	protected function canScan() :bool {
		$can = parent::canScan();
		if ( $can ) {
			$can = $this->assetContext !== null && $this->assetContext->assetType === 'plugin';
		}
		return $can;
	}

	/**
	 * @throws Exceptions\PluginFileUnrecognisedException
	 * @throws Exceptions\PluginFileChecksumFailException
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function runScan() :bool {
		$context = $this->getAssetContext();
		try {
			$verification = $this->getAssetTrustState()->verifyAssetContext( $this->pathFull, $context );
			if ( !$verification->verified ) {
				throw new Exceptions\PluginFileChecksumFailException( $this->pathFull, [
					'slug'          => $verification->assetKey,
					'asset_version' => $verification->assetVersion,
				] );
			}
			$this->hashVerificationResult = $verification;
			return true;
		}
		catch ( UnrecognisedAssetFile $e ) {
			throw new Exceptions\PluginFileUnrecognisedException( $this->pathFull, [
				'slug'          => $context->assetKey,
				'asset_version' => $context->assetVersion,
			] );
		}
	}
}
