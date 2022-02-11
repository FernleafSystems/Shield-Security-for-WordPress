<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Option;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends RouteBase {

	public function getRoutePath() :string {
		return '';
	}

	public function getRoutePathPrefix() :string {
		return '/option';
	}

	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {
		$con = $this->getCon();

		switch ( $reqArgKey ) {

			case 'mod':
				if ( !isset( $con->modules[ $value ] ) ) {
					throw new \Exception( sprintf( "Module doesn't exist: %s", $value ) );
				}
				break;

			case 'key':
				$mod = $request->get_param( 'mod' );
				if ( is_string( $mod ) && isset( $con->modules[ $mod ] ) ) {
					if ( !in_array( $value, $con->modules[ $mod ]->getOptions()->getOptionsKeys() ) ) {
						throw new \Exception( sprintf( "Option doesn't exist: %s", $value ) );
					}
				}
				else {
					throw new \Exception( sprintf( "Cannot process option key for invalid module: %s", $mod ) );
				}
				break;

			default:
				return parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}

		return true;
	}
}