<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class BrowserTestLaneLease {

	/** @var resource|null */
	private $handle;

	private int $laneIndex;

	private string $lockPath;

	private LocalSiteDefinition $definition;

	/**
	 * @param resource $handle
	 */
	public function __construct( int $laneIndex, string $lockPath, $handle, LocalSiteDefinition $definition ) {
		$this->laneIndex = $laneIndex;
		$this->lockPath = $lockPath;
		$this->handle = $handle;
		$this->definition = $definition;
	}

	public function __destruct() {
		$this->release();
	}

	public function definition() :LocalSiteDefinition {
		return $this->definition;
	}

	public function laneIndex() :int {
		return $this->laneIndex;
	}

	public function lockPath() :string {
		return $this->lockPath;
	}

	public function release() :void {
		if ( \is_resource( $this->handle ) ) {
			@\flock( $this->handle, \LOCK_UN );
			@\fclose( $this->handle );
		}
		$this->handle = null;
	}
}
