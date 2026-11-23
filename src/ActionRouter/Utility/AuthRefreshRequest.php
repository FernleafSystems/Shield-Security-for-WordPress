<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Services\Services;

class AuthRefreshRequest {

	private const SERVER_KEY = 'HTTP_X_SHIELD_AUTH_REFRESH';

	public static function isRequested() :bool {
		return Services::Request()->server( self::SERVER_KEY, '' ) === '1';
	}
}
