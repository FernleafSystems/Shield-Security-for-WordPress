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
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		try {
			$bAllowed = ( new TestIp() )
				->setMod( $this->getMod() )
				->runTest( Services::IP()->getRequestIp() );
		}
		catch ( \Exception $oE ) {
			$this->getCon()->fireEvent(
				'request_limit_exceeded',
				[
					'audit' => [
						'count' => $oOpts->getLimitRequestCount(),
						'span'  => $oOpts->getLimitTimeSpan(),
					]
				]
			);
		}
	}
}