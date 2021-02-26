<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class Base {

	use ModConsumer;
	use ExecOnce;

	const SLUG = '';

	protected function canRun() :bool {
		return $this->getCon()->isPremiumActive() && $this->isOptionEnabled() && $this->isPluginInstalled();
	}

	protected function isSpamBot() :bool {
		$isSpam = !$this->getCon()
						->getModule_IPs()
						->getBotSignalsController()
						->verifyNotBot();
		$this->getCon()->fireEvent( sprintf( 'spam_%s_%s',
			static::SLUG, ( $isSpam ? 'fail' : 'pass' ) ) );
		return $isSpam;
	}

	protected function isOptionEnabled() :bool {
		return $this->getOptions()->isOpt( 'spam_'.static::SLUG, 'Y' );
	}

	protected function isPluginInstalled() :bool {
		return false;
	}
}