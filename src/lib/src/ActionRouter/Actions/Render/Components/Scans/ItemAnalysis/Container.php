<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

class Container extends BaseScans {

	public const SLUG = 'scanitemanalysis_container';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/modal_content.twig';

	protected function getRenderData() :array {
		$con = self::con();
		try {
			/** @var ResultItem $item */
			$item = ( new RetrieveItems() )->byID( (int)$this->action_data[ 'rid' ] );
		}
		catch ( \Exception $e ) {
			throw new ActionException( 'Not a valid scan item record' );
		}
		$fragment = $item->path_fragment;
		if ( empty( $fragment ) ) {
			throw new ActionException( 'Non-file scan items are not supported yet.' );
		}

		$common = CommonDisplayStrings::pick( [
			'info_label',
			'history_label',
			'diff_label',
			'contents_label'
		] );

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
				/* translators: %1$s: File text, %2$s: file path fragment */
				'modal_title'      => sprintf( __( '%1$s: %2$s', 'wp-simple-firewall' ), __( 'File', 'wp-simple-firewall' ), $item->path_fragment ),
				'tab_filecontents' => $common[ 'contents_label' ],
				'tab_diff'         => $common[ 'diff_label' ],
				'tab_history'      => $common[ 'history_label' ],
				'tab_info'         => $common[ 'info_label' ],
				'tab_malai'        => __( 'MAL{ai} Lookup', 'wp-simple-firewall' ),
				'file_download'    => __( 'Download File', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'rid'
		];
	}
}
