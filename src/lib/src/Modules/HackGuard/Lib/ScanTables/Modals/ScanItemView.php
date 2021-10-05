<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals;

use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ResultsRetrieve;
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			/** @var Scans\Base\FileResultItem $item */
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

		try {
			$diffContent = $this->getFileDiff( $item );
			$hasDiff = true;
		}
		catch ( \Exception $e ) {
			$diffContent = $e->getMessage();
			$hasDiff = false;
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

		return [
			'path'     => \esc_html( $item->path_fragment ),
			'contents' => $mod->renderTemplate(
				'/wpadmin_pages/insights/scans/modal/scan_item_view/tabpanel.twig',
				[
					'content' => [
						'tab_filecontents' => ( new FileContents() )
												  ->setMod( $mod )
												  ->run( $rid )[ 'contents' ],
						'tab_diff'         => $diffContent,
						'tab_history'      => $historyContent,
					],
					'flags'   => [
						'has_diff'    => $hasDiff,
						'has_history' => $hasHistory,
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

	/**
	 * @param Scans\Base\FileResultItem $resultItem
	 * @return string
	 * @throws \Exception
	 */
	private function getFileDiff( $resultItem ) :string {
		$pathFull = $resultItem->path_full;

		$original = '';
		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isCoreFile( $pathFull ) ) {
			$original = Services::WpFs()->getFileContent(
				( new WpOrg\Wp\Files() )->getOriginalFileFromVcs( $pathFull )
			);
		}
		else {
			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $pathFull );
			if ( !empty( $plugin ) && $plugin->isWpOrg() && $plugin->svn_uses_tags ) {
				$originalFileDownload = ( new WpOrg\Plugin\Files() )->getOriginalFileFromVcs( $pathFull );
				if ( !empty( $originalFileDownload ) ) {
					$original = Services::WpFs()->getFileContent( $originalFileDownload );
				}
			}
			else {
				$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $pathFull );
				if ( !empty( $theme ) && $theme->isWpOrg() ) {
					$originalFileDownload = ( new WpOrg\Theme\Files() )->getOriginalFileFromVcs( $pathFull );
					if ( !empty( $originalFileDownload ) ) {
						$original = Services::WpFs()->getFileContent( $originalFileDownload );
					}
				}
			}
		}

		if ( empty( $original ) ) {
			throw new \Exception( 'There is no original file available to create a diff.' );
		}

		$converter = new ConvertLineEndings();
		$res = ( new Diff() )->getDiff(
			$converter->dosToLinux( $original ),
			$converter->fileDosToLinux( $pathFull )
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