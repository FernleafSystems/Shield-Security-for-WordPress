<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'db_notes_table_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'db_notes_name' );
	}
}