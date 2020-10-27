<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

class SitesListTableHandler extends BaseRender {

	use PluginControllerConsumer;
	use OneTimeExecute;

	/**
	 * @var array
	 */
	private $workingItem;

	protected function run() {
		add_filter( 'mainwp_sitestable_getcolumns', function ( $columns ) {
			$columns[ 'shield' ] = 'Shield';
			return $columns;
		}, 10, 1 );
		add_filter( 'mainwp_sitestable_item', function ( array $item ) {
			$item[ 'shield' ] = $this->renderShieldColumnEntryForItem( $item );
			return $item;
		}, 10, 1 );
	}

	private function renderShieldColumnEntryForItem( array $item ) :string {
		$this->workingItem = $item;
		return $this->render();
	}

	protected function getData() :array {
		$con = $this->getCon();
		$syncData = MainWP_DB::instance()->get_website_option(
			$this->workingItem,
			$con->prefix( 'mainwp-sync' )
		);
		$sync = ( new SyncVO() )->applyFromArray( empty( $syncData ) ? [] : json_decode( $syncData, true ) );

		if ( true || $sync->meta->version !== $this->getCon()->getVersion() ) {
			$out = sprintf( '<a class="ui mini compact button red" href="admin.php?page=managesites&amp;updateid=1">X</a>' );
		}
		else {
			$out = sprintf( '<a class="ui mini compact button %s" href="admin.php?page=managesites&amp;updateid=1">%s</a>',
				$issues > 0 ? 'red' : 'green', $issues );
		}

		return [
			'flags'   => [
				'is_version_match' => $sync->meta->version === $this->getCon()->getVersion()
			],
			'vars'    => [
				'issues'  => array_sum( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] ),
				'version' => $this->getCon()->getVersion()
			],
			'hrefs'   => [
				'this_extension' => Services::WpGeneral()
											->getUrl_AdminPage( $con->mwpVO->official_extension_data[ 'page' ] ),
			],
			'strings' => [
				'tooltip_version_mismatch' => __( "Shield version on site doesn't match this server.", 'wp-simple-firewall' ),
				'tooltip_please_update'    => __( "Please update your Shield plugins to the same versions and re-sync.", 'wp-simple-firewall' ),
				'tooltip_issues_found'     => __( "Issues Found", 'wp-simple-firewall' ),
			]
		];
	}

	protected function getTemplateSlug() :string {
		return 'tables/manage_sites_col';
	}
}