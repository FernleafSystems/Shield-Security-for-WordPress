<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;

class ActionsMap {

	/**
	 * @var array
	 */
	private static $actions = [];

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction|string
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