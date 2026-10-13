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
