<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;

class AdminNotes extends BaseBuild {

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$entries = [];

		foreach ( $this->getEntriesRaw() as $key => $entry ) {
			/** @var EntryVO $entry */
			$e = $entry->getRawData();
			$e[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
			$entries[ $key ] = $e;
		}

		return $entries;
	}

	/**
	 * @return Tables\Render\WpListTable\AdminNotes
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\AdminNotes();
	}
}