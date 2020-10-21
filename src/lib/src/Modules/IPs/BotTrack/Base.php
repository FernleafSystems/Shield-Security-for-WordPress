<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use Shield\Modules\ModConsumer;

	const OPT_KEY = '';

	public function run() {
		$this->process();
	}

	protected function doTransgression() {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$bBlock = $opts->isTrackOptImmediateBlock( static::OPT_KEY );
		if ( $bBlock ) {
			$nCount = 1;
		}
		elseif ( $opts->isTrackOptTransgression( static::OPT_KEY ) ) {
			$nCount = $opts->isTrackOptDoubleTransgression( static::OPT_KEY ) ? 2 : 1;
		}
		else {
			$nCount = 0;
		}

		$this->getCon()
			 ->fireEvent(
				 'bot'.static::OPT_KEY,
				 [
					 'audit'         => $this->getAuditData(),
					 'offense_count' => $nCount,
					 'block'         => $bBlock,
				 ]
			 );
	}

	/**
	 * @return array
	 */
	protected function getAuditData() {
		return [
			'path' => Services::Request()->getPath()
		];
	}

	abstract protected function process();
}
