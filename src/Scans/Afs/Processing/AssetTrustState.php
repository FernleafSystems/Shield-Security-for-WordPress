<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetFileContext,
	AssetTrustResolver,
	Exceptions\NonAssetFileException,
	HashVerificationResult
};

class AssetTrustState {

	private AssetTrustResolver $resolver;

	public function __construct() {
		$this->resolver = new AssetTrustResolver();
	}

	public function resolveAssetContext( string $path ) :?AssetFileContext {
		try {
			$context = $this->resolver->resolveContext( $path );
		}
		catch ( NonAssetFileException $e ) {
			$context = null;
		}
		return $context;
	}

	public function verifyAssetContext( string $path, AssetFileContext $context ) :HashVerificationResult {
		return $this->resolver->verifyContext( $path, $context );
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
		return $context === null ? null : $this->trustedFileContextFromAssetContext( $context );
	}

	private function trustedFileContextFromAssetContext( AssetFileContext $context ) :TrustedFileContext {
		return $this->trustedFileContext(
			$context->assetType,
			$context->assetKey,
			$context->assetVersion,
			$context->relativePath
		);
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
