<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The outcome of one loopback request made by the installer.
 */
final class ProbeResult {

	public bool $reachable;

	public int $status;

	/**
	 * From the verification response: whether the stub ran for that request. Null when the response
	 * was not a valid verification response.
	 */
	public ?bool $loaded;

	public bool $smokeMarkerSeen;

	public function __construct( bool $reachable, int $status, ?bool $loaded = null, bool $smokeMarkerSeen = false ) {
		$this->reachable = $reachable;
		$this->status = $status;
		$this->loaded = $loaded;
		$this->smokeMarkerSeen = $smokeMarkerSeen;
	}

	public static function unreachable() :self {
		return new self( false, 0 );
	}

	public function isServerError() :bool {
		return $this->reachable && $this->status >= 500;
	}

	public function isLoads() :bool {
		return $this->reachable && $this->status > 0 && $this->status < 500;
	}

	public function isVerificationResponse() :bool {
		return $this->reachable && $this->status === 200 && $this->loaded !== null;
	}
}
