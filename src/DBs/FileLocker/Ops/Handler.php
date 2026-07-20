<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public function invalidateTableReadiness() :void {
		static::GetTableReadyCache()->setReady( $this->getTableSchema(), false );
		Services::WpDb()->clearResultShowTables();
	}

	public function tableDelete( bool $truncate = false ) :bool {
		Services::WpDb()->clearResultShowTables();
		$deleted = parent::tableDelete( $truncate );

		if ( !$truncate && Services::WpDb()->tableExists( $this->getTable() ) ) {
			Services::WpDb()->clearResultShowTables();
			$deleted = parent::tableDelete( true );
		}

		$this->invalidateTableReadiness();

		return $deleted;
	}
}
