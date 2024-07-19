<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Exceptions;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Exceptions\NotIpAddressException;
use FernleafSystems\Wordpress\Services\Services;

abstract class IpBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/ips';
	}

	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'ip':
				$srvIP = Services::IP();
				if ( $srvIP->isValidIpRange( $value ) && !$srvIP->isValidIp_PublicRange( $value ) ) {
					throw new NotIpAddressException( 'Not a public IP range' );
				}
				elseif ( !$srvIP->isValidIp_PublicRemote( $value ) ) {
					throw new NotIpAddressException( 'Not a public IP address' );
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