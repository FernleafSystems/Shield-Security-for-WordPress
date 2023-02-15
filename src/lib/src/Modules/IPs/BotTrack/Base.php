<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	public const OPT_KEY = '';

	protected function run() {
		$this->process();
	}

	protected function doTransgression( bool $fireEventOnly = false ) {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$count = 0;
		$block = false;

		if ( !$fireEventOnly ) {
			$block = $opts->isTrackOptImmediateBlock( static::OPT_KEY );
			if ( $block ) {
				$count = 1;
			}
			elseif ( $opts->isTrackOptTransgression( static::OPT_KEY ) ) {
				$count = $opts->isTrackOptDoubleTransgression( static::OPT_KEY ) ? 2 : 1;
			}
		}

		$this->fireEvent( $count, $block );
	}

	protected function fireEvent( $offenseCount = 0, $isBlock = false ) {
		$this->getCon()
			 ->fireEvent(
				 'bot'.static::OPT_KEY,
				 [
					 'audit_params'  => $this->getAuditData(),
					 'offense_count' => $offenseCount,
					 'block'         => $isBlock,
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
