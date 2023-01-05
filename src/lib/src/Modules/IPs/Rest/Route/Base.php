<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\{
	Exceptions
};
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends RouteBase {

	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'ip':
				$srvIP = Services::IP();
				if ( $srvIP->isValidIpRange( $value ) && !$srvIP->isValidIp_PublicRange( $value ) ) {
					throw new Exceptions\NotIpAddressException( 'Not a public IP range' );
				}
				elseif ( !$srvIP->isValidIp_PublicRemote( $value ) ) {
					throw new Exceptions\NotIpAddressException( 'Not a public IP address' );
				}
				break;

			default:
				return parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}

		return true;
	}

	protected function getRouteArgSchema( string $key ) :array {
		switch ( $key ) {
			case 'ip':
				$sch = [
					'description' => 'IP Address',
					'type'        => 'string',
					'format'      => 'ip',
					'required'    => true,
				];
				break;

			default:
				$sch = parent::getRouteArgSchema( $key );
				break;
		}
		return $sch;
	}
}