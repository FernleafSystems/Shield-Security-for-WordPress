<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

class AssetFileContext {

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
}
