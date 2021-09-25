<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Apc extends BaseForAssets {

	const SCAN_SLUG = 'apc';

	/**
	 * @return Scans\Apc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Apc\Utilities\ItemActionHandler();
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'enabled_scan_apc', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Apc\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Apc\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}

	public function buildScanResult( array $rawResult ) :ScanResults\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ScanResults\Ops\Record $record */
		$record = $mod->getDbH_ScanResults()->getRecord();
		$record->meta = $rawResult;
		$record->hash = $rawResult[ 'hash' ];
		$record->item_id = $rawResult[ 'slug' ];
		$record->item_type = $rawResult[ 'context' ] === 'plugins' ? 'p' : 't';
		return $record;
	}
}