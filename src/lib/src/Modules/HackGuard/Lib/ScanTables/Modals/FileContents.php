<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;

class FileContents {

	use ModConsumer;

	/**
	 * @param Scans\Afs\ResultItem $item
	 * @throws \Exception
	 */
	public function run( $item, bool $rawContents = false ) :array {
		if ( empty( $item->path_fragment ) ) {
			throw new \Exception( 'There is no path associated with this record' );
		}

		$path = \path_join( ABSPATH, $item->path_fragment );
		$FS = Services::WpFs();
		if ( !$FS->isFile( $path ) ) {
			throw new \Exception( 'File does not exist.' );
		}

		$contents = $FS->getFileContent( $path );
		if ( empty( $contents ) ) {
			throw new \Exception( 'File is empty or could not be read.' );
		}

		if ( !$rawContents ) {
			$modContents = ( new ConvertLineEndings() )->fileDosToLinux( $path );
			$contents = $this->getMod()->renderTemplate( '/wpadmin_pages/insights/scans/modal/code_block.twig', [
				'lines' => explode( "\n", str_replace( "\t", "    ", $modContents ) ),
			] );
		}
		return [
			'contents' => $contents,
			'path'     => \esc_html( $item->path_fragment ),
		];
	}
}