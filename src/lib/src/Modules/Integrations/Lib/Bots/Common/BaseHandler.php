<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModConsumer;

abstract class BaseHandler {

	use ExecOnce;
	use ModConsumer;

	private static $isBot = null;

	protected function canRun() :bool {
		return static::IsProviderInstalled();
	}

	/**
	 * @return BaseBotDetectionController|mixed
	 */
	abstract public function getHandlerController();

	public static function Slug() :string {
		try {
			$slug = strtolower( ( new \ReflectionClass( static::class ) )->getShortName() );
		}
		catch ( \Exception $e ) {
			$slug = '';
		}
		return $slug;
	}

	public function getHandlerName() :string {
		$name = 'Undefined Name';

		$valueOptions = $this->opts()->getOptDefinition(
			$this->getHandlerController()->getSelectedProvidersOptKey()
		)[ 'value_options' ];

		foreach ( $valueOptions as $valueOption ) {
			if ( $valueOption[ 'value_key' ] === static::Slug() ) {
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
			   && in_array( static::Slug(), $this->getHandlerController()->getSelectedProviders() );
	}

	public static function IsProviderInstalled() :bool {
		return false;
	}

	protected function isProOnly() :bool {
		return true;
	}
}