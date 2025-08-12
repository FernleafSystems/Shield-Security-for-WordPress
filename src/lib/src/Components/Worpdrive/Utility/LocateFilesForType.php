<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

class LocateFilesForType {

	public function find( string $workingDir, string $type ) :array {
		$files = [];
		try {
			$pattern = FileNameFor::For( $type );
			foreach ( new \FilesystemIterator( $workingDir ) as $item ) {
				/** @var \FilesystemIterator $item */
				if ( $item->isFile() && \str_contains( $item->getBasename(), $pattern ) ) {
					$files[] = $item->getPathname();
				}
			}
		}
		catch ( \Exception $e ) {
		}
		return $files;
	}
}