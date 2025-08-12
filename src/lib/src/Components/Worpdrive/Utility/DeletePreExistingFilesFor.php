<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

use FernleafSystems\Wordpress\Services\Services;

class DeletePreExistingFilesFor {

	public function deleteFor( string $workingDir, string $category ) :void {
		try {
			$pattern = FileNameFor::For( $category );
			foreach ( new \FilesystemIterator( $workingDir ) as $item ) {
				/** @var \FilesystemIterator $item */
				if ( $item->isFile() && \str_contains( $item->getBasename(), $pattern ) ) {
					Services::WpFs()->deleteFile( $item->getPathname() );
				}
			}
		}
		catch ( \Exception $e ) {
		}
	}
}