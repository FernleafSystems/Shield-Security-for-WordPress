<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseHandler {

	use ModConsumer;
	use ExecOnce;

	const SLUG = '';

	protected function canRun() :bool {
		return ( $this->getCon()->isPremiumActive() || !$this->isProOnly() )
			   && $this->isEnabled() && static::IsProviderInstalled();
	}

	protected function isBot() :bool {
		return $this->getCon()
					->getModule_IPs()
					->getBotSignalsController()
					->isBot( Services::IP()->getRequestIp() );
	}

	protected function isEnabled() :bool {
		return false;
	}

	public static function IsProviderInstalled() :bool {
		return false;
	}

	protected function getProviderName() :string {
		return '';
	}

	protected function getHandlerSlug() :string {
		try {
			$slug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \Exception $e ) {
			$slug = '';
		}
		return $slug;
	}

	protected function isProOnly() :bool {
		return true;
	}
}