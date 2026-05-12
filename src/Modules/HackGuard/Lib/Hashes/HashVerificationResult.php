<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

class HashVerificationResult {

	public bool $verified;

	public bool $trustedSource;

	public string $assetType;

	public string $assetKey;

	public string $assetVersion;

	public string $relativePath;

	public function __construct(
		bool $verified,
		bool $trustedSource,
		string $assetType,
		string $assetKey,
		string $assetVersion,
		string $relativePath
	) {
		$this->verified = $verified;
		$this->trustedSource = $trustedSource;
		$this->assetType = $assetType;
		$this->assetKey = $assetKey;
		$this->assetVersion = $assetVersion;
		$this->relativePath = $relativePath;
	}
}
