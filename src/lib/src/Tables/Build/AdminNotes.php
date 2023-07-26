<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;

class AdminNotes extends BaseBuild {

	/**
	 * @return Select
	 */
	public function getWorkingSelector() {
		return $this->con()
					->getModule_Plugin()
					->getDbHandler_Notes()
					->getQuerySelector();
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		return \array_map(
			function ( $entry ) {
				/** @var EntryVO $entry */
				$e = $entry->getRawData();
				$e[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
				return $e;
			},
			$this->getEntriesRaw()
		);
	}

	/**
	 * @return Tables\Render\WpListTable\AdminNotes
	 */
	protected function getTableRenderer() {
		return new Tables\Render\WpListTable\AdminNotes();
	}
}