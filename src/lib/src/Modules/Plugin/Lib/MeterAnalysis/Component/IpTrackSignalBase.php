<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

abstract class IpTrackSignalBase extends IpBase {

	protected const SIGNAL_KEY = '';

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_IPs();
		/** @var Options $opts */
		$opts = $mod->opts();
		return parent::testIfProtected() && $opts->getOffenseCountFor( static::SIGNAL_KEY ) > 0;
	}

	protected function getOptConfigKey() :string {
		return static::SIGNAL_KEY;
	}

	public function slug() :string {
		return 'bot_signal_'.static::SIGNAL_KEY;
	}
}