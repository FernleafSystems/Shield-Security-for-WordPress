<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

class ProcessOffense {

	private string $ipAddress = '';

	private bool $hasExecuted = false;

	public function execute() :void {
		if ( !$this->hasExecuted && $this->canRun() ) {
			$this->hasExecuted = true;
			$this->run();
		}
	}

	public function getIP() :string {
		return $this->ipAddress;
	}

	/**
	 * @param string $IP
	 * @return $this
	 */
	public function setIP( $IP ) {
		$this->ipAddress = (string)$IP;
		return $this;
	}

	public function resetExecution() :self {
		$this->hasExecuted = false;
		return $this;
	}

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
	}

	public function incrementOffenses( int $incrementBy, bool $blockIP = false, bool $fireEvents = true ) :void {
	}
}
