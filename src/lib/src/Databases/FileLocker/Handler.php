<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	/**
	 * @return string
	 * @deprecated 11.1
	 */
	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'table_name_filelocker' );
	}
}