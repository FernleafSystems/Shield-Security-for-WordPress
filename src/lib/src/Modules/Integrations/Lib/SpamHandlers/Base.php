<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Base {

	use ModConsumer;
	use ExecOnce;

	const SLUG = '';

	protected function canRun() :bool {
		return $this->getCon()->isPremiumActive() && $this->isOptionEnabled() && $this->isPluginInstalled();
	}

	protected function isSpamBot() :bool {
		return !$this->getCon()
					 ->getModule_IPs()
					 ->getBotSignalsController()
					 ->getHandlerNotBot()
					 ->verify();
	}

	protected function isOptionEnabled() :bool {
		return $this->getOptions()->isOpt( 'spam_'.static::SLUG, 'Y' );
	}

	protected function isPluginInstalled() :bool {
		return false;
	}
}