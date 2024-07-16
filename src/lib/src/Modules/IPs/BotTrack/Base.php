<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use ExecOnce;
	use PluginControllerConsumer;

	public const OPT_KEY = '';

	protected function run() {
		$this->process();
	}

	protected function doTransgression() {
		self::con()->fireEvent(
			'bot'.static::OPT_KEY,
			[
				'audit_params'  => $this->getAuditData(),
				'offense_count' => self::con()->comps->opts_lookup->getBotTrackOffenseCountFor( static::OPT_KEY ),
				'block'         => self::con()->comps->opts_lookup->isBotTrackImmediateBlock( static::OPT_KEY ),
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
