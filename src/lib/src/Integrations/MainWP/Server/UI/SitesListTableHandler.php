<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use MainWP\Dashboard\MainWP_DB;

class SitesListTableHandler {

	use PluginControllerConsumer;
	use OneTimeExecute;

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
		$con = $this->getCon();
		$syncData = MainWP_DB::instance()->get_website_option(
			$item,
			$con->prefix( 'mainwp-sync' )
		);
		$sync = ( new SyncVO() )->applyFromArray( empty( $syncData ) ? [] : json_decode( $syncData, true ) );
		return sprintf( '<a class="ui mini compact button red" href="admin.php?page=managesites&amp;updateid=1">%s</a>',
			$sync->meta->version );
	}
}