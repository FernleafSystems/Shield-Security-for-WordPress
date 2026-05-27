<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\ShieldWorpdriveFilesystem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\WorpdriveTestFilesystemService;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;

class ShieldWorpdriveFilesystemTest extends WorpdriveUnitTestCase {

	protected function setUp() :void {
		parent::setUp();
		ServicesState::mergeItems( [
			'service_wpfs' => new WorpdriveTestFilesystemService(),
		] );
	}

	public function test_filesystem_adapter_delegates_to_shield_filesystem_service() :void {
		$adapter = new ShieldWorpdriveFilesystem();
		$dir = $this->tempDir( 'worpdrive-fs' );
		$file = $dir.'/nested/file.txt';

		$this->assertTrue( $adapter->mkdir( \dirname( $file ) ) );
		$this->assertTrue( $adapter->putFileContent( $file, 'abc' ) );
		$this->assertSame( 'abc', $adapter->getFileContent( $file ) );
		$this->assertTrue( $adapter->isFile( $file ) );
		$this->assertTrue( $adapter->isReadable( $file ) );
		$this->assertSame( 3, $adapter->size( $file ) );
		$this->assertGreaterThan( 0, $adapter->mtime( $file ) );
		$this->assertSame( [ $file ], \array_values( $adapter->enumItemsInDir( \dirname( $file ) ) ) );
		$this->assertTrue( (bool)$adapter->deleteFile( $file ) );
	}

	public function test_filesystem_adapter_preserves_write_check_and_random_file_behaviour() :void {
		$adapter = new ShieldWorpdriveFilesystem();
		$dir = $this->tempDir( 'worpdrive-fs-write' );
		$randomPath = $dir.'/random.bin';

		$this->assertTrue( $adapter->canWriteToDir( $dir ) );
		$this->assertTrue( $adapter->writeRandomBytesFile( $randomPath, 8 ) );
		$this->assertFalse( \is_file( $randomPath ) );
	}
}
