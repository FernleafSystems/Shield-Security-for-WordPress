<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

class WorpdriveTestUrls {

	public function forPluginItem( string $relativePath ) :string {
		return 'https://shield.test/plugin/'.\ltrim( $relativePath, '/' ).'?ver=22.0.0';
	}
}
