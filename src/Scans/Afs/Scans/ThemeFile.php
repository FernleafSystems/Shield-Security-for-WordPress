<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Exceptions\AssetHashesNotFound,
	Exceptions\NonAssetFileException,
	Exceptions\UnrecognisedAssetFile
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

class ThemeFile extends BasePluginThemeFile {

	protected function canScan() :bool {
		$can = parent::canScan();
		if ( $can ) {
			$can = $this->assetContext !== null && $this->assetContext->assetType === 'theme';
		}
		return $can;
	}

	/**
	 * @throws Exceptions\ThemeFileUnrecognisedException
	 * @throws Exceptions\ThemeFileChecksumFailException
	 */
	protected function runScan() :bool {
		$context = $this->getAssetContext();
		try {
			$verification = ( new AssetTrustResolver() )->verifyContext( $this->pathFull, $context );
			if ( !$verification->verified ) {
				throw new Exceptions\ThemeFileChecksumFailException( $this->pathFull, [
					'slug'          => $context->assetKey,
					'asset_version' => $context->assetVersion,
				] );
			}
			$this->hashVerificationResult = $verification;
			$valid = true;
		}
		catch ( UnrecognisedAssetFile $e ) {
			throw new Exceptions\ThemeFileUnrecognisedException( $this->pathFull, [
				'slug'          => $context->assetKey,
				'asset_version' => $context->assetVersion,
			] );
		}
		catch ( \InvalidArgumentException|AssetHashesNotFound|NonAssetFileException $e ) {
			$valid = false;
		}

		return $valid;
	}
}
