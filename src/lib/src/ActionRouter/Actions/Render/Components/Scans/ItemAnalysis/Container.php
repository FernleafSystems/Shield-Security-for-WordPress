<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Services\Services;

class Container extends Base {

	public const SLUG = 'scanitemanalysis_container';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/modal_content.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$item = $this->getScanItem();

		$fullPath = empty( $item->path_full ) ? path_join( ABSPATH, $item->path_fragment ) : $item->path_full;
		return [
			'content' => [
				'tab_info'         => $con->action_router->render( Info::class, [
					'scan_item' => $item
				] ),
				'tab_history'      => $con->action_router->render( History::class, [
					'scan_item' => $item
				] ),
				'tab_filecontents' => $con->action_router->render( Content::class, [
					'scan_item' => $item
				] ),
				'tab_diff'         => $con->action_router->render( Diff::class, [
					'scan_item' => $item
				] ),
				'tab_malai'        => $con->action_router->render( Malai::class, [
					'scan_item' => $item
				] ),
			],
			'flags'   => [
				'can_download'    => Services::WpFs()->isAccessibleFile( $fullPath ),
				'can_query_malai' => self::con()->isPremiumActive() && !$item->is_mal,
			],
			'hrefs'   => [
				'file_download' => $con->plugin_urls->fileDownload( 'scan_file', [ 'rid' => $item->VO->scanresult_id ] ),
			],
			'imgs'    => [
				'svgs' => [
					'file_download' => $con->svgs->raw( 'download.svg' ),
				],
			],
			'strings' => [
				'modal_title'      => sprintf( '%s: %s', 'File', $item->path_fragment ),
				'tab_filecontents' => 'Contents',
				'tab_diff'         => 'Diff',
				'tab_history'      => 'History',
				'tab_info'         => 'Info',
				'tab_malai'        => 'MAL{ai} Lookup',
				'file_download'    => __( 'Download File', 'wp-simple-firewall' ),
			],
		];
	}
}