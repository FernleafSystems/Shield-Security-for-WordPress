<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForFiles extends Base {

	public function buildScanResult( array $rawResult ) :ResultItems\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$autoFiltered = $rawResult[ 'auto_filter' ] ?? false;
		unset( $rawResult[ 'auto_filter' ] );

		/** @var ResultItems\Ops\Record $record */
		$record = $mod->getDbH_ResultItems()->getRecord();
		$record->auto_filtered_at = $autoFiltered ? Services::Request()->ts() : 0;
		$record->hash = $rawResult[ 'hash' ];
		$record->item_id = $rawResult[ 'path_fragment' ];
		$record->item_type = ResultItems\Ops\Handler::ITEM_TYPE_FILE;

		$metaToClear = [
			'path_fragment',
			'path_full',
			'scan',
			'hash',
		];
		foreach ( $metaToClear as $metaItem ) {
			unset( $rawResult[ $metaItem ] );
		}

		$meta = $rawResult;
		if ( !empty( $rawResult[ 'mal_meta' ] ) ) {
			$meta = array_merge( $meta, $rawResult[ 'mal_meta' ] );
			unset( $meta[ 'mal_meta' ] );
		}
		$record->meta = $meta;

		return $record;
	}
}