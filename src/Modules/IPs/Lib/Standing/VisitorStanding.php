<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

/**
 * How far an IP is trusted, and why.
 */
class VisitorStanding {

	public const TRUSTED = 'trusted';
	public const NORMAL = 'normal';
	public const DOUBTFUL = 'doubtful';
	public const HOSTILE = 'hostile';
	public const BLOCKED = 'blocked';

	public const REASON_BYPASS = 'bypass';
	public const REASON_TRUSTED_SERVICE = 'trusted_service';
	public const REASON_MANUAL_BLOCK = 'manual_block';
	public const REASON_CROWDSEC = 'crowdsec';
	public const REASON_CROWDSEC_UNBLOCKED = 'crowdsec_unblocked';
	public const REASON_OFFENSES = 'offenses';
	public const REASON_HOSTILE_HOLD = 'hostile_hold';
	public const REASON_HIGH_REPUTATION = 'high_reputation';

	public string $level;

	/**
	 * @var string[]
	 */
	public array $reasons;

	/**
	 * Faded offense evidence, in offenses.
	 */
	public float $evidence;

	public int $hostileUntil;

	public function __construct( string $level, array $reasons = [], float $evidence = 0.0, int $hostileUntil = 0 ) {
		$this->level = $level;
		$this->reasons = $reasons;
		$this->evidence = $evidence;
		$this->hostileUntil = $hostileUntil;
	}
}
