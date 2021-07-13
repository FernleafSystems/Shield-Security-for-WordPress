<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class TestCacheDirWrite {

	use ModConsumer;

	public function canWrite() :bool {
		return $this->run()->getTestData()[ 'last_success_at' ] > 0;
	}

	/**
	 * @return $this
	 */
	protected function run() {
		$data = $this->getTestData();
		$now = Services::Request()->ts();

		if ( ( $data[ 'last_success_at' ] === 0 || $now - HOUR_IN_SECONDS > $data[ 'last_success_at' ] )
			 && ( $now - HOUR_IN_SECONDS > $data[ 'last_test_at' ] ) ) {

			$rootDir = $this->getCon()->getPluginCachePath();
			$canWrite = !empty( $rootDir )
						&& $this->canCreateWriteDeleteFile()
						&& $this->canCreateWriteDeleteDir();

			$data[ 'last_success_at' ] = $canWrite ? $now : 0;
			$data[ 'last_test_at' ] = $now;
			$this->getOptions()->setOpt( 'cache_dir_write_test', $data );
		}
		return $this;
	}

	private function canCreateWriteDeleteDir() :bool {
		$canWrite = false;

		$FS = Services::WpFs();

		$testDir = $this->getCon()->getPluginCachePath( uniqid() );
		$FS->mkdir( $testDir );
		if ( $FS->isDir( $testDir ) ) {
			$file = path_join( $testDir, uniqid() );
			$FS->touch( $file );
			$canTouchFile = $FS->isFile( $file );
			$FS->deleteDir( $testDir );
			$canWrite = $canTouchFile && !$FS->isDir( $testDir );
		}
		return $canWrite;
	}

	private function canCreateWriteDeleteFile() :bool {
		$canWrite = false;

		$FS = Services::WpFs();

		$testFile = $this->getCon()->getPluginCachePath( 'test_write_file.txt' );
		$uniq = uniqid();
		$FS->putFileContent( $testFile, $uniq );
		if ( $FS->getFileContent( $testFile ) == $uniq ) {
			$FS->deleteFile( $testFile );
			$canWrite = !$FS->exists( $testFile );
		}
		return $canWrite;
	}

	private function getTestData() :array {
		$data = $this->getOptions()->getOpt( 'cache_dir_write_test' );
		return array_merge(
			[
				'last_test_at'    => 0,
				'last_success_at' => 0,
			],
			is_array( $data ) ? $data : []
		);
	}
}
