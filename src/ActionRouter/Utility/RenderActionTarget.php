<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class RenderActionTarget {

	/**
	 * @return class-string<BaseRender>|''
	 */
	public static function resolve( string $classOrSlug ) :string {
		$action = ActionsMap::ActionFromSlug( $classOrSlug );

		return !empty( $action ) && \is_a( $action, BaseRender::class, true ) ? $action : '';
	}

	/**
	 * @return class-string<BaseRender>
	 * @throws ActionException
	 */
	public static function require( string $classOrSlug ) :string {
		$action = self::resolve( $classOrSlug );
		if ( empty( $action ) ) {
			throw new ActionException( __( 'Invalid render target.', 'wp-simple-firewall' ) );
		}
		return $action;
	}
}
