<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
class AssessDirWrite {

	private $dir;

	/**
	 * @throws \Exception
	 */
	public function __construct( string $dir ) {
		if ( !path_is_absolute( $dir ) ) {
			throw new \Exception( 'Not an absolute path' );
		}
		$this->dir = wp_normalize_path( $dir );
		if ( $this->dir === '/' ) {
			throw new \Exception( "We don't test root dirs" );
		}
	}

	public function test() :array {
		$FS = Services::WpFs();
		$create = ( $FS->isDir( $this->dir ) || ( $FS->mkdir( $this->dir ) && $FS->isDir( $this->dir ) ) );
		return [
			'create_dir'     => $create,
			'writeable_file' => $create && $this->canCreateWriteDeleteFile(),
			'writeable'      => $create && $this->canCreateWriteDeleteDir(),
		];
	}

	private function canCreateWriteDeleteDir() :bool {
		$canWrite = false;

		$FS = Services::WpFs();

		$testDir = \path_join( $this->dir, 'test-dir' );
		if ( $FS->isAccessibleFile( $testDir ) ) {
			$FS->deleteFile( $testDir );
		}
		if ( $FS->isDir( $testDir ) ) {
			$FS->deleteDir( $testDir );
		}

		$FS->mkdir( $testDir );
		if ( $FS->isDir( $testDir ) ) {
			$file = \path_join( $testDir, uniqid() );
			$FS->touch( $file );
			$canTouchFile = $FS->isAccessibleFile( $file );
			$FS->deleteFile( $file );
			$FS->deleteDir( $testDir );
			\clearstatcache();
			$canWrite = $canTouchFile && !$FS->isAccessibleDir( $testDir );
		}
		return $canWrite;
	}

	private function canCreateWriteDeleteFile() :bool {
		$FS = Services::WpFs();

		$canWrite = false;
		$testFile = \path_join( $this->dir, 'test_write_file.txt' );
		$uniq = uniqid();
		$FS->putFileContent( $testFile, $uniq );
		if ( $FS->isAccessibleFile( $testFile ) ) {
			$canWrite = $FS->getFileContent( $testFile ) == $uniq;
			$FS->deleteFile( $testFile );
			$canWrite = $canWrite && !$FS->exists( $testFile );
		}

		return $canWrite;
	}
}