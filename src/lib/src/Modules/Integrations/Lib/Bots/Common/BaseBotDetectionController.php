<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseBotDetectionController {

	use ExecOnce;
	use PluginControllerConsumer;

	private array $installedProviders;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions;
	}

	/**
	 * @return BaseHandler[]|string[]
	 */
	public function getInstalled() :array {
		return $this->installedProviders ??= \array_filter( $this->enumProviders(), fn( string $p ) => $p::IsProviderAvailable() );
	}

	protected function run() {
		\array_map(
			fn( string $providerClass ) => ( new $providerClass() )->execute(),
			\array_intersect_key( $this->getInstalled(), \array_flip( $this->getSelectedProviders() ) )
		);
	}

	/**
	 * @return string[]
	 */
	public function getSelectedProviders() :array {
		return self::con()->opts->optGet( $this->getSelectedProvidersOptKey() );
	}

	abstract public function getSelectedProvidersOptKey() :string;

	/**
	 * @return BaseHandler[]|string[]
	 */
	public function enumProviders() :array {
		return [];
	}

	protected function isEnabled() :bool {
		return !empty( $this->getSelectedProviders() );
	}
}