<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'table_columns_filelocker' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'table_name_filelocker' );
	}
}