<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Find;

abstract class BaseHandler {

	use ExecOnce;
	use PluginControllerConsumer;

	private static ?bool $isBot = null;

	private ?bool $isCooldown = null;

	protected function canRun() :bool {
		return static::ProviderMeetsRequirements();
	}

	/**
	 * @return BaseBotDetectionController|mixed
	 */
	abstract public function getHandlerController();

	public static function Slug() :string {
		try {
			$slug = \strtolower( ( new \ReflectionClass( static::class ) )->getShortName() );
		}
		catch ( \Exception $e ) {
			$slug = '';
		}
		return $slug;
	}

	public function getHandlerName() :string {
		$name = 'Undefined Name';

		$valueOptions = self::con()->opts->optDef(
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
		if ( \is_null( self::$isBot ) ) {
			self::$isBot = self::con()->comps->bot_signals->isBot( self::con()->this_req->ip );
			$this->fireBotEvent();
		}
		return self::$isBot;
	}

	abstract protected function fireBotEvent();

	abstract protected function getCooldownContext() :string;

	protected function isBotBlockEnabled() :bool {
		return $this->isEnabled();
	}

	public function isBotBlockRequired() :bool {
		return $this->isCoolDownBlockRequired() || ( $this->isBot() && $this->isBotBlockEnabled() );
	}

	public function isCoolDownBlockRequired() :bool {
		$required = false;
		if ( self::con()->comps->cool_down->isCooldownContextEnabled( $this->getCooldownContext() ) ) {
			if ( $this->isCooldown === null ) {
				if ( $this->isCooldown = self::con()->comps->cool_down->isCooldownTriggered( $this->getCooldownContext() ) ) {
					self::con()->comps->events->fireEvent( 'cooldown_fail' );
				}
			}
			$required = $this->isCooldown;
		}
		return $required;
	}

	public function isEnabled() :bool {
		return ( self::con()->isPremiumActive() || !$this->isProOnly() )
			   && \in_array( static::Slug(), $this->getHandlerController()->getSelectedProviders() );
	}

	public static function IsProviderAvailable() :bool {
		return static::IsProviderInstalled() && static::ProviderMeetsRequirements();
	}

	public static function IsProviderInstalled() :bool {
		return static::Slug() === 'wordpress' || ( new Find() )->isPluginActive( static::Slug() );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return true;
	}

	protected function isProOnly() :bool {
		return static::Slug() !== 'wordpress';
	}
}