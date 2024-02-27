<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\AvailableCiphers;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;

class GetAvailableCiphers {

	private static $local = null;

	private static $snapi = null;

	public function full() :array {
		return \array_values( \array_intersect(
			$this->snapi(),
			$this->local()
		) );
	}

	public function firstFull() :?string {
		$first = \current( $this->full() );
		return \is_string( $first ) ? $first : null;
	}

	public function local() :array {
		if ( self::$local === null ) {
			self::$local = ( new CipherTests() )->findAvailableCiphers();
		}
		return self::$local;
	}

	public function snapi() :array {
		if ( self::$snapi === null ) {
			self::$snapi = ( new AvailableCiphers() )->retrieve();
		}
		return self::$snapi;
	}
}