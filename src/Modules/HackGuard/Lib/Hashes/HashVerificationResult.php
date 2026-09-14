<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

class HashVerificationResult {

	public const COMPARISON_BASIS_PUBLISHED_REFERENCE = 'published_reference';
	public const COMPARISON_BASIS_LOCAL_BASELINE = 'local_baseline';

	public bool $verified;

	public bool $trustedSource;

	public bool $recognisedInSnapshot;

	public string $comparisonBasis;

	public string $assetType;

	public string $assetKey;

	public string $assetVersion;

	public string $relativePath;

	public function __construct(
		bool $verified,
		bool $trustedSource,
		bool $recognisedInSnapshot,
		string $comparisonBasis,
		string $assetType,
		string $assetKey,
		string $assetVersion,
		string $relativePath
	) {
		$this->verified = $verified;
		$this->trustedSource = $trustedSource;
		$this->recognisedInSnapshot = $recognisedInSnapshot;
		$this->comparisonBasis = $comparisonBasis;
		$this->assetType = $assetType;
		$this->assetKey = $assetKey;
		$this->assetVersion = $assetVersion;
		$this->relativePath = $relativePath;
	}
}
