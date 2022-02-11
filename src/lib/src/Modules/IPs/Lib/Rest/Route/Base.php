<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\{
	Exceptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends RouteBase {

	public function getRoutePathPrefix() :string {
		return '/ip_lists';
	}

	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'ip':
				$srvIP = Services::IP();
				if ( !$srvIP->isValidIpOrRange( $value ) ) {
					throw new Exceptions\NotIpAddressException( 'Not a valid IP address or IP range' );
				}
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
}