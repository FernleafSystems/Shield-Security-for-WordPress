<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;

class ActionsMap {

	private static array $actions = [];

	/**
	 * @return string|class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction>
	 */
	public static function ActionFromSlug( string $classOrSlug ) :string {
		if ( !isset( self::$actions[ $classOrSlug ] ) ) {
			if ( \class_exists( $classOrSlug ) ) {
				self::$actions[ $classOrSlug::SLUG ] = $classOrSlug;
				$classOrSlug = $classOrSlug::SLUG;
			}
			else {
				foreach ( Constants::ACTIONS as $action ) {
					if ( \class_exists( $action ) && $action::SLUG === $classOrSlug ) {
						self::$actions[ $classOrSlug ] = $action;
						break;
					}
				}
			}
		}
		return self::$actions[ $classOrSlug ] ?? '';
	}
}