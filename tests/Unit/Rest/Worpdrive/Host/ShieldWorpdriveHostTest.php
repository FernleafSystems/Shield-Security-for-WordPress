<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\{
	ShieldWorpdriveDatabase,
	ShieldWorpdriveFilesystem,
	ShieldWorpdriveHost,
	ShieldWorpdriveWordPress
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;

class ShieldWorpdriveHostTest extends WorpdriveUnitTestCase {

	public function test_host_preserves_shield_owned_runtime_values() :void {
		$pluginRoot = $this->tempDir( 'worpdrive-host-plugin' );
		$cacheDir = $this->tempDir( 'worpdrive-host-cache' );
		$this->installController( $pluginRoot, $cacheDir );

		$host = new ShieldWorpdriveHost();

		$this->assertSame( $pluginRoot.'/', $host->rootDir() );
		$this->assertSame( 'tmp/archive-unit-uuid/', $host->baseArchivePath( 'unit-uuid' ) );
		$this->assertSame( '22.1-test', $host->pluginVersion() );
		$this->assertSame(
			'https://shield.test/plugin/tmp/archive-unit-uuid/full_map_db.sqlite3',
			$host->pluginUrlForItem( 'tmp/archive-unit-uuid/full_map_db.sqlite3' )
		);
		$this->assertSame( $cacheDir, $host->cacheDir() );
		$this->assertSame( '2222', $host->uniqueId( 4 ) );
	}

	public function test_host_exposes_shield_adapter_instances() :void {
		$host = new ShieldWorpdriveHost();

		$this->assertInstanceOf( ShieldWorpdriveFilesystem::class, $host->filesystem() );
		$this->assertSame( $host->filesystem(), $host->filesystem() );
		$this->assertInstanceOf( ShieldWorpdriveDatabase::class, $host->database() );
		$this->assertSame( $host->database(), $host->database() );
		$this->assertInstanceOf( ShieldWorpdriveWordPress::class, $host->wordpress() );
		$this->assertSame( $host->wordpress(), $host->wordpress() );
	}
}
