<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ActionsMap;

/**
 * A request can only name a registered action. A test that sends a request for one of its own action classes
 * registers it here first, and unregisters it when it's done.
 */
trait RegistersTestActions {

	/**
	 * @var string[]
	 */
	private array $registeredTestActionSlugs = [];

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction> ...$actionClasses
	 */
	protected function registerTestActions( string ...$actionClasses ) :void {
		$registered = $this->requestableActions();
		$map = $registered->getValue();
		foreach ( $actionClasses as $actionClass ) {
			$map[ $actionClass::SLUG ] = $actionClass;
			$this->registeredTestActionSlugs[] = $actionClass::SLUG;
		}
		$registered->setValue( null, $map );
	}

	protected function unregisterTestActions() :void {
		$registered = $this->requestableActions();
		$registered->setValue( null, \array_diff_key( $registered->getValue(), \array_flip( $this->registeredTestActionSlugs ) ) );
		$this->registeredTestActionSlugs = [];
	}

	private function requestableActions() :\ReflectionProperty {
		$registered = new \ReflectionProperty( ActionsMap::class, 'registered' );
		$registered->setAccessible( true );
		return $registered;
	}
}
