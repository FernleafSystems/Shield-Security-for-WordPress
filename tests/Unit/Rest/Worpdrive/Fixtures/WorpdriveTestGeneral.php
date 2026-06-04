<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\General;

class WorpdriveTestGeneral extends General {

	public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
		return 'https://home.test/'.$path;
	}

	public function getWpUrl( string $path = '' ) :string {
		return 'https://wp.test/'.$path;
	}
}
