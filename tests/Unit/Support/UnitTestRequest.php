<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Services\Core\Request;

class UnitTestRequest extends Request {

	public function __construct(
		private array $queryValues = [],
		private string $ipAddress = '127.0.0.1',
		private int $timestamp = 1700000000,
	) {
	}

	public function query( $key, $default = null ) {
		return $this->queryValues[ $key ] ?? $default;
	}

	public function ip() :string {
		return $this->ipAddress;
	}

	public function ts( bool $update = true ) :int {
		return $this->timestamp;
	}

	public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
		return new Carbon( 'now', 'UTC' );
	}
}
