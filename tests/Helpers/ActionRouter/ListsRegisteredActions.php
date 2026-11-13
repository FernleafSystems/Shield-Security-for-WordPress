<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\BaseAction,
	Constants
};

/**
 * The plugin's registered actions that can run: the ones a request can name.
 */
trait ListsRegisteredActions {

	/**
	 * @return list<class-string<BaseAction>>
	 */
	private function registeredActions() :array {
		return \array_values( \array_filter(
			\array_unique( Constants::ACTIONS ),
			static fn( string $action ) :bool => \class_exists( $action ) && !( new \ReflectionClass( $action ) )->isAbstract()
		) );
	}
}
