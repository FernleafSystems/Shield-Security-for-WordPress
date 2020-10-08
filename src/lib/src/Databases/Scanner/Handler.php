<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'table_columns_scanner' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'table_name_scanner' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'scanresults_table_timestamp_columns' );
	}
}