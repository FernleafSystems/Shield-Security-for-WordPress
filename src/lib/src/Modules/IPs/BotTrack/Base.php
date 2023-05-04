<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use ExecOnce;
	use ModConsumer;

	public const OPT_KEY = '';

	protected function run() {
		$this->process();
	}

	protected function doTransgression( bool $fireEventOnly = false ) {
		$opts = $this->opts();

		$count = 0;
		$block = false;

		if ( !$fireEventOnly ) {
			$block = $this->opts()->isTrackOptImmediateBlock( static::OPT_KEY );
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
		$this->con()
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
