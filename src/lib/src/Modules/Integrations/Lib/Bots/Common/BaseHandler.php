<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseHandler extends ExecOnceModConsumer {

	private static $isBot = null;

	protected function canRun() :bool {
		return static::IsProviderInstalled();
	}

	/**
	 * @return BaseBotDetectionController|mixed
	 */
	abstract public function getHandlerController();

	public function getHandlerSlug() :string {
		try {
			$slug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \Exception $e ) {
			$slug = '';
		}
		return $slug;
	}

	public function getHandlerName() :string {
		$name = 'Undefined Name';
		$slug = $this->getHandlerSlug();

		$valueOptions = $this->getOptions()
							 ->getOptDefinition(
								 $this->getHandlerController()->getSelectedProvidersOptKey()
							 )[ 'value_options' ];

		foreach ( $valueOptions as $valueOption ) {
			if ( $valueOption[ 'value_key' ] === $slug ) {
				$name = __( $valueOption[ 'text' ], 'wp-simple-firewall' );
				break;
			}
		}
		return $name;
	}

	protected function isBot() :bool {
		if ( is_null( self::$isBot ) ) {
			self::$isBot = $this->getCon()
								->getModule_IPs()
								->getBotSignalsController()
								->isBot( $this->getCon()->this_req->ip );
			$this->fireBotEvent();
		}
		return self::$isBot;
	}

	abstract protected function fireBotEvent();

	protected function isBotBlockEnabled() :bool {
		return $this->isEnabled();
	}

	public function isBotBlockRequired() :bool {
		return $this->isBot() && $this->isBotBlockEnabled();
	}

	protected function isSpam_Human() :bool {
		return false;
	}

	public function isEnabled() :bool {
		return ( $this->getCon()->isPremiumActive() || !$this->isProOnly() )
			   && in_array( $this->getHandlerSlug(), $this->getHandlerController()->getSelectedProviders() );
	}

	public static function IsProviderInstalled() :bool {
		return false;
	}

	protected function isProOnly() :bool {
		return true;
	}
}