<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

class TrustedFileContext {

	public string $assetType;

	public string $assetKey;

	public string $assetVersion;

	public string $relativePath;

	public function __construct(
		string $assetType,
		string $assetKey,
		string $assetVersion,
		string $relativePath
	) {
		$this->assetType = $assetType;
		$this->assetKey = $assetKey;
		$this->assetVersion = $assetVersion;
		$this->relativePath = $relativePath;
	}

	public function key() :string {
		return \hash( 'sha256', \implode( '|', [
			$this->assetType,
			$this->assetKey,
			$this->assetVersion,
			$this->relativePath,
		] ) );
	}
}
