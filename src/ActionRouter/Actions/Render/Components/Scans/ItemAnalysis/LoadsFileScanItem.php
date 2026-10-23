<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;

trait LoadsFileScanItem {

	/**
	 * @throws ActionException
	 */
	protected function loadFileScanItem() :ResultItem {
		try {
			$item = ( new RetrieveItems() )->byID( (int)$this->action_data[ 'rid' ] );
		}
		catch ( \Exception $e ) {
			throw new ActionException( 'Not a valid scan item record' );
		}
		if ( !$item instanceof ResultItem || empty( $item->path_fragment ) ) {
			throw new ActionException( 'Non-file scan items are not supported yet.' );
		}
		return $item;
	}
}
