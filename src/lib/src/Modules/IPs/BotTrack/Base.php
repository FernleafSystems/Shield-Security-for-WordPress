<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use Shield\Modules\ModConsumer;
	use ExecOnce;

	const OPT_KEY = '';

	protected function run() {
		$this->process();
	}

	protected function doTransgression() {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$block = $opts->isTrackOptImmediateBlock( static::OPT_KEY );
		if ( $block ) {
			$count = 1;
		}
		elseif ( $opts->isTrackOptTransgression( static::OPT_KEY ) ) {
			$count = $opts->isTrackOptDoubleTransgression( static::OPT_KEY ) ? 2 : 1;
		}
		else {
			$count = 0;
		}

		$this->getCon()
			 ->fireEvent(
				 'bot'.static::OPT_KEY,
				 [
					 'audit'         => $this->getAuditData(),
					 'offense_count' => $count,
					 'block'         => $block,
				 ]
			 );
	}

	protected function getAuditData() :array {
		return [
			'path' => Services::Request()->getPath()
		];
	}

	abstract protected function process();
}
