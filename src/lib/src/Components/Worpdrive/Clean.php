<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive;

use FernleafSystems\Wordpress\Services\Services;

class Clean extends BaseHandler {

	public function run() :array {
		$this->deleteOtherArchivesFromWorkingDirContainer();
		$this->cleanWorkingDir();
		return [];
	}

	public function deleteOtherArchivesFromWorkingDirContainer() {
		$FS = Services::WpFs();
		foreach ( $FS->enumItemsInDir( \dirname( $this->workingDir() ) ) as $path ) {
			if ( \is_dir( $path )
				 && \str_starts_with( \basename( $path ), 'archive-' )
				 && \basename( $path ) !== \basename( $this->workingDir() )
			) {
				$FS->delete( $path );
			}
		}
	}

	/**
	 * This should be the final call, as any other calls to ->workingDir() will recreate that dir.
	 */
	public function cleanWorkingDir() {
		Services::WpFs()->deleteDir( $this->workingDir() );
	}
}