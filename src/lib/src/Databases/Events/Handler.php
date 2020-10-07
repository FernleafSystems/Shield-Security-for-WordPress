<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Options;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	/**
	 * @param $aEvents - array of events: key event slug, value created_at timestamp
	 */
	public function commitEvents( $aEvents ) {
		foreach ( $aEvents as $sEvent => $nTs ) {
			$this->commitEvent( $sEvent, 1, $nTs );
		}
	}

	/**
	 * @param string $evt
	 * @param null   $nTs
	 * @param int    $nCount
	 * @return bool
	 */
	public function commitEvent( string $evt, $nCount = 1, $nTs = null ) {
		if ( empty( $nTs ) || !is_numeric( $nTs ) ) {
			$nTs = Services::Request()->ts();
		}

		/** @var EntryVO $oEvt */
		$oEvt = $this->getVo();
		$oEvt->event = $evt;
		$oEvt->count = max( 1, (int)$nCount );
		$oEvt->created_at = max( 1, $nTs );
		/** @var Insert $QI */
		$QI = $this->getQueryInserter();
		return $QI->insert( $oEvt );
	}

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'events_table_columns' );
	}

	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_Events();
	}
}