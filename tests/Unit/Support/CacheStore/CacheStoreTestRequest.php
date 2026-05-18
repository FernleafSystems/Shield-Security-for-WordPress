<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

use FernleafSystems\Wordpress\Services\Core\Request;

class CacheStoreTestRequest extends Request {

	private int $timestamp = 1700000000;

	public function __construct( int $timestamp = 1700000000 ) {
		$this->timestamp = $timestamp;
		parent::__construct();
	}

	public function ts( bool $update = true ) :int {
		unset( $update );
		return $this->timestamp;
	}
}
