<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetFileContext,
	AssetTrustResolver,
	Exceptions\NonAssetFileException,
	HashVerificationResult
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;

class AssetTrustState {

	private AssetTrustResolver $resolver;

	private ScanActionVO $action;

	public function __construct( ScanActionVO $action ) {
		$this->action = $action;
		$this->resolver = new AssetTrustResolver();
	}

	public function resolveAssetContext( string $path ) :?AssetFileContext {
		try {
			$context = $this->resolver->resolveCurrentContext( $path );
		}
		catch ( NonAssetFileException $e ) {
			$context = null;
		}
		return $context;
	}

	/**
	 * @throws NonAssetFileException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function verifyAssetContext( string $path, AssetFileContext $context ) :?HashVerificationResult {
		if ( \in_array( $context->assetType, [ 'plugin', 'theme' ], true ) ) {
			if ( $this->action->scope_type !== 'full' ) {
				return $this->resolver->verifyStoredContext( $path, $context );
			}
			if ( !$this->action->hasValidAssetSnapshotEligibility()
				 || !$this->action->hasValidAssetComparisonIncomplete()
				 || $this->action->isAssetComparisonIncomplete( $context->assetType, $context->assetKey ) ) {
				return null;
			}

			$entry = $this->action->asset_snapshot_eligibility[ $context->assetType ][ $context->assetKey ] ?? null;
			if ( !\is_array( $entry ) ) {
				$this->action->markAssetComparisonIncomplete( $context->assetType, $context->assetKey );
				return null;
			}
			if ( $entry[ 'version' ] !== $context->assetVersion ) {
				$this->action->markAssetComparisonIncomplete( $context->assetType, $context->assetKey );
				return null;
			}
			if ( !$entry[ 'comparison_eligible' ] ) {
				return null;
			}

			$verification = $this->resolver->verifyStoredContext( $path, $context );
			if ( \is_null( $verification ) ) {
				$this->action->markAssetComparisonIncomplete( $context->assetType, $context->assetKey );
			}
			return $verification;
		}
		return $this->resolver->verifyStoredContext( $path, $context );
	}

	public function trustedFileContextFromVerification( HashVerificationResult $verification ) :TrustedFileContext {
		return $this->trustedFileContext(
			$verification->assetType,
			$verification->assetKey,
			$verification->assetVersion,
			$verification->relativePath
		);
	}

	public function trustedFileContextForAssetPath( string $path ) :?TrustedFileContext {
		$context = $this->resolveAssetContext( $path );
		if ( $context === null ) {
			return null;
		}

		$verification = $this->verifyAssetContext( $path, $context );
		return $verification === null || !$verification->verified || !$verification->trustedSource
			? null
			: $this->trustedFileContextFromVerification( $verification );
	}

	private function trustedFileContext(
		string $assetType,
		string $assetKey,
		string $assetVersion,
		string $relativePath
	) :TrustedFileContext {
		return new TrustedFileContext(
			$assetType,
			$assetKey,
			$assetVersion,
			$relativePath
		);
	}
}
