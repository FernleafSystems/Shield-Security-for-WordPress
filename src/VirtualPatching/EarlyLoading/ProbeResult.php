<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The outcome of one loopback request made by the installer.
 */
final readonly class ProbeResult {

	/**
	 * @param ?bool $loaded from a verification response, whether the stub ran for that request; null when the
	 *                      response was not a valid verification response
	 */
	public function __construct(
		public bool $reachable,
		public int $status,
		public ?bool $loaded = null,
		public bool $smokeMarkerSeen = false
	) {
	}

	public static function unreachable() :self {
		return new self( false, 0 );
	}

	/**
	 * A stub requested directly answers with only its marker. An error status, or other output with status
	 * 200 (a displayed fatal error, for example), means the server can't run it. A refused or failed request
	 * proves nothing either way.
	 */
	public function smokeFailed() :bool {
		return $this->reachable && ( $this->status >= 500 || ( $this->status === 200 && !$this->smokeMarkerSeen ) );
	}

	public function isVerificationResponse() :bool {
		return $this->reachable && $this->status === 200 && $this->loaded !== null;
	}
}
