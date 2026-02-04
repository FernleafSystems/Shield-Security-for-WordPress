<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class IpTrackSignalBase extends IpBase {

	protected const SIGNAL_KEY = '';

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->getBotTrackOffenseCountFor( $this->getOptConfigKey() ) > 0;
	}

	protected function getOptConfigKey() :string {
		return static::SIGNAL_KEY;
	}

	public function slug() :string {
		return 'bot_signal_'.static::SIGNAL_KEY;
	}
}