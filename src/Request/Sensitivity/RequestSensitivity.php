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

	/**
	 * Open, but judged before WordPress routed the request. Only routing shows whether a pretty URL is
	 * content or an endpoint, so it must be judged again once the request is routed.
	 */
	public bool $isProvisional;

	public function __construct( string $level, string $reason, bool $isProvisional = false ) {
		$this->level = $level;
		$this->reason = $reason;
		$this->isProvisional = $isProvisional;
	}
}
