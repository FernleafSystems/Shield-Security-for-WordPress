<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use ParagonIE\ConstantTime\Base64UrlSafe;

final class PasskeyBase64Url {

	private function __construct() {
	}

	public static function encode( string $data ) :string {
		return Base64UrlSafe::encodeUnpadded( $data );
	}

	public static function decode( string $encoded ) :string {
		return Base64UrlSafe::decode( $encoded );
	}
}
