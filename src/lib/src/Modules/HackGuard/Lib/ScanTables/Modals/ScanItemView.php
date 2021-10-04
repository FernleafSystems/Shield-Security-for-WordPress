<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Query;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ResultsRetrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Util\Diff;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Wp\Files;

class ScanItemView {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( int $rid ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$item = ( new ResultsRetrieve() )
				->setMod( $mod )
				->byID( $rid );
		}
		catch ( \Exception $e ) {
			throw new \Exception( 'Not a valid record' );
		}

		if ( empty( $item->path_full ) ) {
			throw new \Exception( 'Non-file scan items are not supported yet.' );
		}

		return [
			'path'     => \esc_html( $item->path_fragment ),
			'contents' => $mod->renderTemplate(
				'/wpadmin_pages/insights/scans/modal/scan_item_view/scan_item_tabpanel.twig',
				[
					'content' => [
						'tab_filecontents' => ( new FileContents() )
												  ->setMod( $mod )
												  ->run( $rid )[ 'contents' ],
						'tab_diff'         => $this->getFileDiff( $item->path_full ),
						'tab_history'      => 'HISTORY',
					],
					'strings' => [
						'tab_filecontents' => 'Contents',
						'tab_diff'         => 'Diff',
						'tab_history'      => 'History',
					],
				]
			),
		];
	}

	private function getFileDiff( string $pathFull ) :string {
		$original = '';
		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isCoreFile( $pathFull ) ) {
			// TODO: Convoluted - get the content directly
			$original = Services::WpFs()->getFileContent(
				( new Files() )->getOriginalFileFromVcs( $pathFull )
			);
		}
		else {
			// TODO for plugins/themes
			$exists = ( new Query() )
				->setMod( $this->getMod() )
				->fileExistsInHash( $pathFull );
		}

		if ( empty( $original ) ) {
			$diff = 'no diff';
		}
		else {
			$res = ( new Diff() )->getDiff( $original, Services::WpFs()->getFileContent( $pathFull ) );
			if ( !is_array( $res ) || empty( $res[ 'html' ] ) ) {
				throw new \Exception( 'Could not get a valid diff for this file.' );
			}
			$diff = sprintf( '<style>%s</style>%s',
				'table.diff.diff-wrapper tbody tr td:nth-child(2){ width:auto;}'.
				'table.diff.diff-wrapper { table-layout: auto;}'.
				base64_decode( $res[ 'html' ][ 'css_default' ] ),
				base64_decode( $res[ 'html' ][ 'content' ] )
			);
		}

		return $diff;
	}
}