<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

use FernleafSystems\Wordpress\Services\Core\General;

class CacheStoreTestWpGeneral extends General {

	public string $url;

	public function __construct( string $url = 'https://example.test/' ) {
		$this->url = $url;
	}

	public function getWpUrl( string $path = '' ) :string {
		return $this->url.$path;
	}
}
