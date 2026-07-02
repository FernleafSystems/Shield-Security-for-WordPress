<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const DB_KEY = 'import_export_profiles';

	protected function run() {
		if ( $this->use_table_ready_cache ) {
			Services::WpDb()->clearResultShowTables();
			$schema = $this->getTableSchema();
			if ( static::GetTableReadyCache()->isReady( $schema ) && !$this->tableExists() ) {
				static::GetTableReadyCache()->setReady( $schema, false );
			}
		}

		parent::run();
	}
}
