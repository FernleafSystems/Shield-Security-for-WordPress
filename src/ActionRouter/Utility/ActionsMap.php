<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;

class ActionsMap {

	/**
	 * Registered actions already found, by slug.
	 * @var array<string,class-string<BaseAction>>
	 */
	private static array $registered = [];

	/**
	 * The registered action a request names. A request names an action by its slug and nothing else, so the name
	 * is only ever compared with the slugs of registered actions: it is never treated as a class name.
	 * @return string|class-string<BaseAction> empty when no registered action has that slug
	 */
	public static function ActionFromRequest( string $slug ) :string {
		if ( !isset( self::$registered[ $slug ] ) && ActionData::isValidActionSlug( $slug ) ) {
			foreach ( Constants::ACTIONS as $action ) {
				if ( \class_exists( $action ) && $action::SLUG === $slug ) {
					self::$registered[ $slug ] = $action;
					break;
				}
			}
		}
		return self::$registered[ $slug ] ?? '';
	}

	/**
	 * The action the plugin's own code names: a registered action by its slug, or any action by its class. Not
	 * for a name taken from a request: see ActionFromRequest().
	 * @return string|class-string<BaseAction>
	 */
	public static function ActionFromSlug( string $classOrSlug ) :string {
		$action = self::ActionFromRequest( $classOrSlug );
		if ( $action === '' && \class_exists( $classOrSlug ) && \is_a( $classOrSlug, BaseAction::class, true ) ) {
			$action = $classOrSlug;
		}
		return $action;
	}
}
