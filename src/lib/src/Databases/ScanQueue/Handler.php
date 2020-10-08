<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'table_columns_scanqueue' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'table_name_scanqueue' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'scanqueue_table_timestamp_columns' );
	}
}