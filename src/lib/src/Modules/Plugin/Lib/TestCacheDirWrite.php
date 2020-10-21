<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TestCacheDirWrite
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib
 */
class TestCacheDirWrite {

	use ModConsumer;

	public function canWrite() :bool {
		return $this->run()->getTestData()[ 'last_success_at' ] > 0;
	}

	/**
	 * @return $this
	 */
	protected function run() {
		$aD = $this->getTestData();
		$nNow = Services::Request()->ts();

		if ( ( $aD[ 'last_success_at' ] === 0 || $nNow - WEEK_IN_SECONDS > $aD[ 'last_success_at' ] )
			 && ( $nNow - HOUR_IN_SECONDS > $aD[ 'last_test_at' ] ) ) {

			$sRoot = $this->getCon()->getPath_PluginCache();
			$bCanWrite = !empty( $sRoot )
						 && $this->canCreateWriteDeleteFile()
						 && $this->canCreateWriteDeleteDir();

			$aD[ 'last_success_at' ] = $bCanWrite ? $nNow : 0;
			$aD[ 'last_test_at' ] = $nNow;
			$this->getOptions()->setOpt( 'cache_dir_write_test', $aD );
		}
		return $this;
	}

	private function canCreateWriteDeleteDir() :bool {
		$bCanWrite = false;

		$oFS = Services::WpFs();

		$sTestDir = $this->getCon()->getPluginCachePath( uniqid() );
		$oFS->mkdir( $sTestDir );
		if ( $oFS->isDir( $sTestDir ) ) {
			$sFile = path_join( $sTestDir, uniqid() );
			$oFS->touch( $sFile );
			$oFS->deleteDir( $sTestDir );
			$bCanWrite = !$oFS->isDir( $sTestDir );
		}
		return $bCanWrite;
	}

	private function canCreateWriteDeleteFile() :bool {
		$bCanWrite = false;

		$oFS = Services::WpFs();

		$sTestFile = $this->getCon()->getPluginCachePath( 'test_write_file.txt' );
		$oFS->touch( $sTestFile );

		if ( $oFS->exists( $sTestFile ) ) {
			$sUniq = uniqid();
			$oFS->putFileContent( $sTestFile, $sUniq );
			if ( $oFS->getFileContent( $sTestFile ) == $sUniq ) {
				$oFS->deleteFile( $sTestFile );
				$bCanWrite = !$oFS->exists( $sTestFile );
			}
		}
		return $bCanWrite;
	}

	private function getTestData() :array {
		$aD = $this->getOptions()->getOpt( 'cache_dir_write_test' );
		return array_merge(
			[
				'last_test_at'    => 0,
				'last_success_at' => 0,
			],
			is_array( $aD ) ? $aD : []
		);
	}
}
