<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Services\Services;

class Container extends Base {

	public const SLUG = 'scanitemanalysis_container';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/modal_content.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$item = $this->getScanItem();
		$actionRouter = $this->getCon()
							 ->getModule_Insights()
							 ->getActionRouter();

		$fullPath = empty( $item->path_full ) ? path_join( ABSPATH, $item->path_fragment ) : $item->path_full;
		return [
			'content' => [
				'tab_filecontents' => $actionRouter->render( Content::SLUG, [
					'scan_item' => $item
				] ),
				'tab_diff'         => $actionRouter->render( Diff::SLUG, [
					'scan_item' => $item
				] ),
				'tab_history'      => $actionRouter->render( History::SLUG, [
					'scan_item' => $item
				] ),
				'tab_info'         => $actionRouter->render( Info::SLUG, [
					'scan_item' => $item
				] ),
			],
			'flags'   => [
				'can_download' => Services::WpFs()->isFile( $fullPath ),
			],
			'hrefs'   => [
				'file_download' => $con->plugin_urls->fileDownload( 'scan_file', [ 'rid' => $item->VO->scanresult_id ] ),
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
		];
	}
}