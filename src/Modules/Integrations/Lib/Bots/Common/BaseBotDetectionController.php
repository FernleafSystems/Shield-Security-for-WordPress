<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseBotDetectionController {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var array<string,class-string<BaseHandler>>
	 */
	private array $installedProviders;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions
			   && $this->isEnabled();
	}

	/**
	 * @return array<string,class-string<BaseHandler>>
	 */
	public function getInstalled() :array {
		return $this->installedProviders ??= \array_filter(
			$this->enumProviders(),
			static fn( string $p ) => $p::IsProviderAvailable()
		);
	}

	protected function run() {
		foreach ( \array_intersect_key( $this->enumProviders(), \array_flip( $this->getSelectedProviders() ) ) as $providerClass ) {
			if ( $providerClass::IsProviderAvailable() ) {
				( new $providerClass() )->execute();
			}
		}
	}

	/**
	 * @return string[]
	 */
	public function getSelectedProviders() :array {
		return self::con()->opts->optGet( $this->getSelectedProvidersOptKey() );
	}

	abstract public function getSelectedProvidersOptKey() :string;

	/**
	 * @return array<int,array{value_key:string,text:string}>
	 */
	public function providerOptions() :array {
		return self::con()->opts->optDef( $this->getSelectedProvidersOptKey() )[ 'value_options' ] ?? [];
	}

	/**
	 * @return array<string,class-string<BaseHandler>>
	 */
	public function enumProviders() :array {
		return [];
	}

	protected function isEnabled() :bool {
		return !empty( $this->getSelectedProviders() );
	}
}
