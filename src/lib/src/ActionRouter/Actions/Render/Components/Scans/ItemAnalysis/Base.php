<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;

abstract class Base extends BaseScans {

	protected static $ScanItem;

	/**
	 * @throws ActionException
	 */
	protected function getScanItem() :ResultItem {
		if ( !isset( static::$ScanItem ) ) {
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

			static::$ScanItem = $item;
		}

		return static::$ScanItem;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'rid'
		];
	}
}