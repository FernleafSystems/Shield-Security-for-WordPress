<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class Handler extends Base\EnumeratedColumnsHandler {

	public function getColumnsAsArray() :array {
		return $this->getOptions()->getDef( 'db_notes_table_columns' );
	}

	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_Notes();
	}
}