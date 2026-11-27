<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\NonAssetFileException;
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
	 * @throws NonAssetFileException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function runScan() :bool {
		$context = $this->getAssetContext();
		$verification = $this->getAssetTrustState()->verifyAssetContext( $this->pathFull, $context );
		if ( \is_null( $verification ) ) {
			return true;
		}
		if ( !$verification->recognisedInSnapshot ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: the message is a file path, caught by FileScanner and turned into a scan result item, never shown.
			throw new Exceptions\PluginFileUnrecognisedException( $this->pathFull, [
				'slug'             => $verification->assetKey,
				'asset_version'    => $verification->assetVersion,
				'comparison_basis' => $verification->comparisonBasis,
			] );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( !$verification->verified ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: the message is a file path, caught by FileScanner and turned into a scan result item, never shown.
			throw new Exceptions\PluginFileChecksumFailException( $this->pathFull, [
				'slug'             => $verification->assetKey,
				'asset_version'    => $verification->assetVersion,
				'comparison_basis' => $verification->comparisonBasis,
			] );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$this->hashVerificationResult = $verification;
		return true;
	}
}
