<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Limiter
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit
 */
class Limiter {

	use ModConsumer;

	public function run() {
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isTrafficLimitEnabled() ) {
			add_action( 'init', [ $this, 'limit' ] );
		}
	}

	public function limit() {
		try {
			( new TestIp() )
				->setMod( $this->getMod() )
				->runTest( Services::IP()->getRequestIp() );
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