<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

abstract class IpTrackSignalBase extends IpBase {

	protected const SIGNAL_KEY = '';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_IPs();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return parent::isProtected() && $opts->getOffenseCountFor( static::SIGNAL_KEY ) > 0;
	}

	public function href() :string {
		return $this->getCon()->getModule_IPs()->isModOptEnabled() ?
			$this->link( static::SIGNAL_KEY ) : parent::href();
	}

	public function slug() :string {
		return 'bot_signal_'.static::SIGNAL_KEY;
	}
}