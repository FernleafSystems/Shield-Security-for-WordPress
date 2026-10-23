<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans;
use FernleafSystems\Wordpress\Services\Services;

class Container extends BaseScans {

	use LoadsFileScanItem;

	public const SLUG = 'scanitemanalysis_container';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/modal_content.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$item = $this->loadFileScanItem();

		return [
			'content' => [
				'tab_info'         => $con->action_router->render( Info::class, [
					'scan_item' => $item
				] ),
				'tab_filecontents' => $con->action_router->render( Content::class, [
					'scan_item' => $item
				] ),
				'tab_malai'        => $con->action_router->render( Malai::class, [
					'scan_item' => $item
				] ),
			],
			'flags'   => [
				'can_download'    => Services::WpFs()->isAccessibleFile( $item->path_full ),
				'can_query_malai' => self::con()->isPremiumActive() && !$item->is_mal,
			],
			'hrefs'   => [
				'file_download' => $con->plugin_urls->fileDownload( 'scan_file', [ 'rid' => $item->VO->resultitem_id ] ),
			],
			'vars'    => [
				'modal_title_id'     => 'ShieldModalContainerLabel',
				'diff_render_action' => (string)\wp_json_encode( ActionData::BuildAjaxRender( Diff::class, [
					'rid' => $item->VO->resultitem_id,
				] ) ),
			],
			'imgs'    => [
				'svgs' => [
					'file_download' => $con->svgs->iconClass( 'download.svg' ),
				],
			],
			'strings' => [
				/* translators: %1$s: File text, %2$s: file path fragment */
				'modal_title'      => sprintf( __( '%1$s: %2$s', 'wp-simple-firewall' ), __( 'File', 'wp-simple-firewall' ), $item->path_fragment ),
				'tab_filecontents' => __( 'File Contents', 'wp-simple-firewall' ),
				'tab_diff'         => __( 'Changes (Diff)', 'wp-simple-firewall' ),
				'tab_info'         => __( 'Overview', 'wp-simple-firewall' ),
				'tab_malai'        => __( 'MAL{ai} Lookup', 'wp-simple-firewall' ),
				'file_download'    => __( 'Download File', 'wp-simple-firewall' ),
				'diff_loading'     => __( 'Preparing the comparison...', 'wp-simple-firewall' ),
				'diff_failed'      => __( "The comparison couldn't be loaded. Please try again.", 'wp-simple-firewall' ),
				'diff_retry'       => __( 'Try again', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'rid'
		];
	}
}
