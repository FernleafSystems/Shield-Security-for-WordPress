<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals;

use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Util\Diff;

class ScanItemView {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( int $rid ) :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			/** @var Scans\Afs\ResultItem $item */
			$item = ( new Retrieve() )
				->setMod( $mod )
				->byID( $rid );
		}
		catch ( \Exception $e ) {
			throw new \Exception( 'Not a valid record' );
		}

		if ( empty( $item->path_fragment ) ) {
			throw new \Exception( 'Non-file scan items are not supported yet.' );
		}

		try {
			$diffContent = $this->getFileDiff( $item );
			$hasDiff = true;
		}
		catch ( \Exception $e ) {
			$diffContent = $e->getMessage();
			$hasDiff = false;
		}

		try {
			$fileContent = ( new FileContents() )
							   ->setMod( $mod )
							   ->run( $item )[ 'contents' ];
			$hasContent = true;
		}
		catch ( \Exception $e ) {
			$fileContent = $e->getMessage();
			$hasContent = false;
		}

		try {
			$historyContent = ( new BuildHistory() )
				->setMod( $this->getMod() )
				->run( $item );
			$hasHistory = true;
		}
		catch ( \Exception $e ) {
			$historyContent = $e->getMessage();
			$hasHistory = false;
		}

		$fullPath = empty( $item->path_full ) ? path_join( ABSPATH, $item->path_fragment ) : $item->path_full;
		return [
			'path'     => \esc_html( $item->path_fragment ),
			'contents' => $mod->renderTemplate( '/wpadmin_pages/insights/scans/modal/scan_item_view/modal_content.twig', [
				'content' => [
					'tab_filecontents' => $fileContent,
					'tab_diff'         => $diffContent,
					'tab_history'      => $historyContent,
					'tab_info'         => ( new BuildInfo() )
						->setMod( $this->getMod() )
						->setScanItem( $item )
						->run(),
				],
				'flags'   => [
					'can_download' => Services::WpFs()->isFile( $fullPath ),
					'has_content'  => $hasContent,
					'has_diff'     => $hasDiff,
					'has_history'  => $hasHistory,
				],
				'hrefs'   => [
					'file_download' => $mod->getScanCon( $item->VO->scan )
										   ->createFileDownloadLink( $item->VO->scanresult_id ),
					'has_content'   => $hasContent,
					'has_diff'      => $hasDiff,
					'has_history'   => $hasHistory,
				],
				'imgs'    => [
					'svgs' => [
						'file_download' => $con->svgs->raw( 'bootstrap/download.svg' ),
					],
				],
				'strings' => [
					'modal_title'      => sprintf( '%s: %s', 'File', $item->path_fragment ),
					'tab_filecontents' => 'Contents',
					'tab_diff'         => 'Diff',
					'tab_history'      => 'History',
					'tab_info'         => 'Info',
					'file_download'    => __( 'Download File', 'wp-simple-firewall' ),
				],
			] )
		];
	}

	/**
	 * @param Scans\Afs\ResultItem $item
	 * @throws \Exception
	 */
	private function getFileDiff( $item ) :string {
		$pathFull = empty( $item->path_full ) ? path_join( ABSPATH, $item->path_fragment ) : $item->path_full;

		if ( $item->is_missing || !Services::WpFs()->isFile( $pathFull ) ) {
			throw new \Exception( 'Diff is unavailable for missing files.' );
		}

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isCoreFile( $pathFull ) ) {
			$originalFileDownload = ( new WpOrg\Wp\Files() )->getOriginalFileFromVcs( $pathFull );
		}
		else {
			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $pathFull );
			if ( !empty( $plugin ) && $plugin->isWpOrg() && $plugin->svn_uses_tags ) {
				$originalFileDownload = ( new WpOrg\Plugin\Files() )->getOriginalFileFromVcs( $pathFull );
			}
			else {
				$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $pathFull );
				if ( !empty( $theme ) && $theme->isWpOrg() ) {
					$originalFileDownload = ( new WpOrg\Theme\Files() )->getOriginalFileFromVcs( $pathFull );
				}
			}
		}

		$FS = Services::WpFs();
		if ( empty( $originalFileDownload ) || !$FS->isFile($originalFileDownload)) {
			throw new \Exception( "A File Diff can't be created as there is no original/official file available for us to compare with." );
		}

		$conv = new ConvertLineEndings();
		$res = ( new Diff() )->getDiff(
			$conv->dosToLinux( (string)$FS->getFileContent( $originalFileDownload ) ),
			$conv->fileDosToLinux( $pathFull )
		);

		if ( !is_array( $res ) || empty( $res[ 'html' ] ) ) {
			throw new \Exception( 'Could not get a valid diff for this file.' );
		}

		return sprintf( '<style>%s</style>%s',
			'table.diff.diff-wrapper tbody tr td:nth-child(2){ width:auto;}'.
			'table.diff.diff-wrapper { table-layout: auto;}'.
			base64_decode( $res[ 'html' ][ 'css_default' ] ),
			base64_decode( $res[ 'html' ][ 'content' ] )
		);
	}
}