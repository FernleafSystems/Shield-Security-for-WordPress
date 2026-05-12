<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

class IpTelemetryMode {
	public const LEGACY_SIGNALS = 'legacy_signals';
	public const CANONICAL_EVIDENCE = 'canonical_evidence';

	public static function all() :array {
		return [
			self::LEGACY_SIGNALS,
			self::CANONICAL_EVIDENCE,
		];
	}
}
