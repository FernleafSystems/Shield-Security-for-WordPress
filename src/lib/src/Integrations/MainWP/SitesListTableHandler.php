<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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
		return '<a class="ui mini compact button red" href="admin.php?page=managesites&amp;updateid=1">5</a>';
	}
}
