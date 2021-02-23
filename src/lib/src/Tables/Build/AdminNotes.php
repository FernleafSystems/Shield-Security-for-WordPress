<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;

/**
 * Class AdminNotes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class AdminNotes extends BaseBuild {

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$aEntries = [];

		foreach ( $this->getEntriesRaw() as $key => $entry ) {
			/** @var EntryVO $entry */
			$e = $entry->getRawData();
			$e[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
			$aEntries[ $key ] = $e;
		}

		return $aEntries;
	}

	/**
	 * @return Tables\Render\WpListTable\AdminNotes
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\AdminNotes();
	}
}