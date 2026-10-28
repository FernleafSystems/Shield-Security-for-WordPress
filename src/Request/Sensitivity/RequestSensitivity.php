<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

/**
 * How much harm a request could do, and why it was judged so.
 */
class RequestSensitivity {

	/**
	 * Viewing content, with no input.
	 */
	public const OPEN = 'open';

	/**
	 * Takes input, or runs code for anonymous visitors.
	 */
	public const GUARDED = 'guarded';

	/**
	 * Authentication of any kind, admin requests, and Shield's own actions.
	 */
	public const CRITICAL = 'critical';

	public const LEVELS = [
		self::OPEN,
		self::GUARDED,
		self::CRITICAL,
	];

	public string $level;

	public string $reason;

	public function __construct( string $level, string $reason ) {
		$this->level = $level;
		$this->reason = $reason;
	}
}
