<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ConvertBetweenTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class RetrieveFileContents {

	use ModConsumer;

	/**
	 * @param int  $rid
	 * @param bool $raw
	 * @return array
	 * @throws \Exception
	 */
	public function retrieve( int $rid, bool $raw = false ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var EntryVO $record */
		$record = $mod->getDbHandler_ScanResults()
					  ->getQuerySelector()
					  ->byId( $rid );
		if ( empty( $record ) ) {
			throw new \Exception( 'Not a valid file record' );
		}
		$item = ( new ConvertBetweenTypes() )
			->setScanController( $mod->getScanCon( $record->scan ) )
			->convertVoToResultItem( $record );
		$path = $item->path_full;
		if ( empty( $path ) ) {
			throw new \Exception( 'There is no path associated with this record' );
		}
		$FS = Services::WpFs();
		if ( !$FS->isFile( $path ) ) {
			throw new \Exception( 'File does not exist.' );
		}
		$contents = $FS->getFileContent( $path );
		if ( empty( $contents ) ) {
			throw new \Exception( 'File is empty or could not be read.' );
		}
		if ( !$raw ) {
			$modContents = Services::DataManipulation()->convertLineEndingsDosToLinux( $path );
			$contents = $this->getMod()
							 ->renderTemplate(
								 '/wpadmin_pages/insights/scans/modal/code_block.twig',
								 [
									 'lines'    => explode( "\n", str_replace( "\t", "    ", $modContents ) ),
								 ]
							 );
		}
		return [
			'contents' => $contents,
			'path'     => esc_html( $item->path_fragment ),
		];
	}
}