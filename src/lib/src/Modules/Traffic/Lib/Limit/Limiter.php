<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Services\Services;

class Limiter extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var Traffic\Options $opts */
		$opts = $this->getOptions();
		return $opts->isTrafficLimitEnabled();
	}

	protected function run() {
		add_action( 'init', [ $this, 'limit' ] );
	}

	public function limit() {
		try {
			( new TestIp() )
				->setMod( $this->getMod() )
				->setIP( Services::IP()->getRequestIp() )
				->runTest();
		}
		catch ( \Exception $e ) {
			/** @var Traffic\Options $opts */
			$opts = $this->getOptions();
			$this->getCon()->fireEvent(
				'request_limit_exceeded',
				[
					'audit_params' => [
						'count' => $opts->getLimitRequestCount(),
						'span'  => $opts->getLimitTimeSpan(),
					]
				]
			);
		}
	}
}