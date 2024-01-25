<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\AvailableCiphers;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;

class GetAvailableCiphers {

	private static $ciphers = null;

	public function run() :array {
		if ( self::$ciphers === null ) {
			self::$ciphers = \array_values( \array_intersect(
				( new AvailableCiphers() )->retrieve(),
				( new CipherTests() )->findAvailableCiphers()
			) );
		}
		return self::$ciphers;
	}

	public function first() :?string {
		$first = \current( $this->run() );
		return \is_string( $first ) ? $first : null;
	}
}