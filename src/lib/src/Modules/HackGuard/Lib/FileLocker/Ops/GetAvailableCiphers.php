<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\AvailableCiphers;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\CipherTests;

class GetAvailableCiphers {

	private static array $local;

	private static array $snapi;

	public function full() :array {
		return \array_values( \array_intersect(
			self::$snapi ??= ( new AvailableCiphers() )->retrieve(),
			self::$local ??= ( new CipherTests() )->findAvailableCiphers()
		) );
	}

	public function firstFull() :?string {
		$first = \current( $this->full() );
		return \is_string( $first ) ? $first : null;
	}

	/**
	 * @deprecated 20.1
	 */
	public function local() :array {
		return self::$local ??= ( new CipherTests() )->findAvailableCiphers();
	}

	/**
	 * @deprecated 20.1
	 */
	public function snapi() :array {
		return self::$snapi ??= ( new AvailableCiphers() )->retrieve();
	}
}