<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Process;

class StandardShieldCentralByCallable extends \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\Base {

	protected \Closure $processCallable;

	public function setProcessCallable( \Closure $callable ) :self {
		$this->processCallable = $callable;
		return $this;
	}

	protected function process() :array {
		return \call_user_func( $this->processCallable, $this->getWpRestRequest() );
	}
}
