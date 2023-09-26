<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 18.4.2
 */
abstract class BaseForFiles extends Base {

	public function buildScanResult( array $rawResult ) :ResultItems\Ops\Record {
		$autoFiltered = $rawResult[ 'auto_filter' ] ?? false;

		/** @var ResultItems\Ops\Record $record */
		$record = $this->mod()->getDbH_ResultItems()->getRecord();
		$record->auto_filtered_at = $autoFiltered ? Services::Request()->ts() : 0;
		$record->item_id = $rawResult[ 'path_fragment' ];
		$record->item_type = ResultItems\Ops\Handler::ITEM_TYPE_FILE;

		$metaToClear = [
			'auto_filter',
			'path_full',
			'scan',
			'hash',
		];
		foreach ( $metaToClear as $metaItem ) {
			unset( $rawResult[ $metaItem ] );
		}

		$meta = $rawResult;
		$record->meta = $meta;

		return $record;
	}
}