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

	public function test_can_write_to_dir_preserves_pre_existing_probe_named_children() :void {
		$adapter = new ShieldWorpdriveFilesystem();
		$dir = $this->tempDir( 'worpdrive-fs-preserve' );
		$existingDir = $dir.'/test-dir';
		$existingFile = $existingDir.'/test_write_file.txt';
		\mkdir( $existingDir, 0777, true );
		\file_put_contents( $existingFile, 'existing content' );

		$this->assertTrue( $adapter->canWriteToDir( $dir ) );

		$this->assertTrue( \is_dir( $existingDir ) );
		$this->assertSame( 'existing content', \file_get_contents( $existingFile ) );
		$this->assertSame( [ 'test-dir' ], $this->directoryBasenames( $dir ) );
	}

	public function test_can_write_to_dir_removes_only_owned_empty_created_directories() :void {
		$adapter = new ShieldWorpdriveFilesystem();
		$baseDir = $this->tempDir( 'worpdrive-fs-created-parent' );
		$probeDir = $baseDir.'/owned/probe';

		$this->assertTrue( $adapter->canWriteToDir( $probeDir ) );

		$this->assertFalse( \is_dir( $probeDir ) );
		$this->assertFalse( \is_dir( $baseDir.'/owned' ) );
		$this->assertTrue( \is_dir( $baseDir ) );
	}

	public function test_write_random_bytes_file_preserves_existing_target() :void {
		$adapter = new ShieldWorpdriveFilesystem();
		$dir = $this->tempDir( 'worpdrive-fs-existing-random' );
		$randomPath = $dir.'/random.bin';
		\file_put_contents( $randomPath, 'do not replace' );

		$this->assertTrue( $adapter->writeRandomBytesFile( $randomPath, 8 ) );

		$this->assertSame( 'do not replace', \file_get_contents( $randomPath ) );
		$this->assertSame( [ 'random.bin' ], $this->directoryBasenames( $dir ) );
	}

	public function test_probe_cleanup_preserves_created_directory_when_unexpected_sibling_appears() :void {
		ServicesState::mergeItems( [
			'service_wpfs' => new class extends WorpdriveTestFilesystemService {
				public function deleteFile( $path ) {
					@\file_put_contents( path_join( \dirname( (string)$path ), 'external.txt' ), 'external' );
					return parent::deleteFile( $path );
				}
			},
		] );
		$adapter = new ShieldWorpdriveFilesystem();
		$baseDir = $this->tempDir( 'worpdrive-fs-cleanup-interrupted' );
		$probeDir = $baseDir.'/created';

		$this->assertTrue( $adapter->canWriteToDir( $probeDir ) );

		$this->assertTrue( \is_dir( $probeDir ) );
		$this->assertSame( 'external', \file_get_contents( $probeDir.'/external.txt' ) );
		$this->assertSame( [ 'external.txt' ], $this->directoryBasenames( $probeDir ) );
	}

	public function test_write_random_bytes_file_cleans_owned_target_and_preserves_unrelated_sibling() :void {
		$adapter = new ShieldWorpdriveFilesystem();
		$dir = $this->tempDir( 'worpdrive-fs-sibling' );
		$siblingPath = $dir.'/keep.txt';
		$randomPath = $dir.'/random.bin';
		\file_put_contents( $siblingPath, 'keep' );

		$this->assertTrue( $adapter->writeRandomBytesFile( $randomPath, 8 ) );

		$this->assertSame( 'keep', \file_get_contents( $siblingPath ) );
		$this->assertFalse( \is_file( $randomPath ) );
		$this->assertSame( [ 'keep.txt' ], $this->directoryBasenames( $dir ) );
	}

	private function directoryBasenames( string $dir ) :array {
		$items = \array_map( '\basename', \glob( \rtrim( $dir, '/\\' ).'/*' ) ?: [] );
		\sort( $items );
		return $items;
	}
}
