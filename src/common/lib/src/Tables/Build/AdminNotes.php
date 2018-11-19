<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Tables;

/**
 * Class AdminNotes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class AdminNotes extends Base {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var \ICWP_WPSF_NoteVO $oEntry */
			$aE = $oEntry->getRawData();
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Tables\Render\AdminNotes
	 */
	protected function getTableRenderer() {
		return new Tables\Render\AdminNotes();
	}
}