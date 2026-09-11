<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

class CompiledReportAssetReadiness {

	private SourceAssetBuildReadiness $assetBuildReadiness;

	private ?string $readyRoot = null;

	public function __construct( ?SourceAssetBuildReadiness $assetBuildReadiness = null ) {
		$this->assetBuildReadiness = $assetBuildReadiness ?? new SourceAssetBuildReadiness();
	}

	public function ensureReady( string $pluginRoot ) :void {
		$normalizedRoot = Path::canonicalize( $pluginRoot );
		if ( $this->readyRoot === $normalizedRoot ) {
			return;
		}

		$requiredBundle = Path::join( $normalizedRoot, 'assets', 'dist', 'shield-reports.bundle.js' );
		$sourceOuterReceipt = \getenv( 'SHIELD_SKIP_INNER_SETUP' ) === '1'
			&& \getenv( 'SHIELD_TEST_MODE' ) === 'docker';
		$packagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		$packageOuterReceipt = \is_string( $packagePath ) && \trim( $packagePath ) !== '';

		if ( !$sourceOuterReceipt && !$packageOuterReceipt ) {
			$this->assetBuildReadiness->ensureReady(
				$normalizedRoot,
				null,
				'PHP integration tests that render compiled reports'
			);
		}

		\clearstatcache( true, $requiredBundle );
		if ( !\is_file( $requiredBundle ) || !\is_readable( $requiredBundle ) || (int)\filesize( $requiredBundle ) < 1 ) {
			throw new \RuntimeException(
				'Required compiled report asset is missing, unreadable, or empty: '.$requiredBundle
			);
		}

		$this->readyRoot = $normalizedRoot;
	}
}
