<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Ops\TableIndices;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const DB_KEY = 'import_export_sites';

	public const STATUS_ACTIVE = 'active';
	public const STATUS_DELETED = 'deleted';

	public const QUEUE_IDLE = 'idle';
	public const QUEUE_QUEUED = 'queued';
	public const QUEUE_PROCESSING = 'processing';
	public const QUEUE_WAITING_EXPORT = 'waiting_export';

	public const SOURCE_LEGACY_OPTION = 'legacy_option';
	public const SOURCE_MANUAL = 'manual';
	public const SOURCE_EXPORT = 'export';

	public const EXPORT_RESULT_SUCCESS = 'success';
	public const EXPORT_RESULT_VERIFY_FAILED = 'verify_failed';
	public const EXPORT_RESULT_EXCEPTION = 'exception';
	public const EXPORT_RESULT_TIMEOUT = 'export_timeout';

	private bool $isImportExportSitesReady = false;

	protected function run() {
		$schema = $this->getTableSchema();
		if ( $this->use_table_ready_cache ) {
			Services::WpDb()->clearResultShowTables();
			if ( static::GetTableReadyCache()->isReady( $schema ) ) {
				if ( $this->tableExists() ) {
					$this->isImportExportSitesReady = true;
					return;
				}
				static::GetTableReadyCache()->setReady( $schema, false );
			}
		}

		$this->applyAddOnlySchemaAlignment();
		$this->isImportExportSitesReady = $this->isAddOnlySchemaReady();

		if ( $this->isImportExportSitesReady ) {
			static::GetTableReadyCache()->setReady( $schema );
		}
	}

	public function isReady() :bool {
		return $this->isImportExportSitesReady;
	}

	public function reset() {
		$this->isImportExportSitesReady = false;
		parent::reset();
	}

	private function applyAddOnlySchemaAlignment() :void {
		$DB = Services::WpDb();
		$schema = $this->getTableSchema();

		if ( !$DB->tableExists( $schema->table ) ) {
			$DB->doSql( $schema->buildCreate() );
			$DB->clearResultShowTables();
		}
		else {
			$this->addMissingColumns();
		}

		$this->addMissingIndexes();
		$this->addMissingUniqueUrlHashIndex();
	}

	private function addMissingColumns() :void {
		$schema = $this->getTableSchema();
		$actual = \array_map( '\strtolower', Services::WpDb()->getColumnsForTable( $schema->table ) );
		$previousColumn = '';

		foreach ( $schema->enumerateColumns() as $column => $definition ) {
			if ( !\in_array( \strtolower( $column ), $actual, true ) ) {
				Services::WpDb()->doSql( sprintf(
					'ALTER TABLE `%s` ADD COLUMN `%s` %s %s;',
					$schema->table,
					$column,
					$definition,
					empty( $previousColumn ) ? 'FIRST' : sprintf( 'AFTER `%s`', $previousColumn )
				) );
				$actual[] = \strtolower( $column );
			}
			$previousColumn = $column;
		}
	}

	private function addMissingIndexes() :void {
		$schema = $this->getTableSchema();
		$indices = new TableIndices( $schema );

		foreach ( $schema->indices as $index ) {
			$keyName = (string)( $index[ 'key_name' ] ?? '' );
			$columns = \is_array( $index[ 'columns' ] ?? null ) ? $index[ 'columns' ] : [];
			if ( empty( $keyName ) || empty( $columns ) ) {
				continue;
			}

			try {
				if ( !$indices->exists( $columns, \strtolower( $keyName ) ) ) {
					$indices->addForColumns( $columns, $keyName );
				}
			}
			catch ( \Throwable $e ) {
			}
		}
	}

	private function addMissingUniqueUrlHashIndex() :void {
		if ( !$this->tableExists() || $this->hasUniqueIndexForColumns( [ 'url_hash' ] ) ) {
			return;
		}

		try {
			Services::WpDb()->doSql( sprintf(
				'CREATE UNIQUE INDEX `%s` ON `%s` (`url_hash`);',
				'url_hash',
				$this->getTableSchema()->table
			) );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function isAddOnlySchemaReady() :bool {
		try {
			$schema = $this->getTableSchema();
			if ( !$this->tableExists() ) {
				return false;
			}

			$actual = \array_map( '\strtolower', Services::WpDb()->getColumnsForTable( $schema->table ) );
			if ( \count( \array_diff( $schema->getColumnNames(), $actual ) ) > 0 ) {
				return false;
			}

			$indices = new TableIndices( $schema );
			foreach ( $schema->indices as $index ) {
				$keyName = (string)( $index[ 'key_name' ] ?? '' );
				$columns = \is_array( $index[ 'columns' ] ?? null ) ? $index[ 'columns' ] : [];
				if ( !empty( $keyName ) && !empty( $columns ) && !$indices->exists( $columns, \strtolower( $keyName ) ) ) {
					return false;
				}
			}

			return $this->hasUniqueIndexForColumns( [ 'url_hash' ] );
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	private function hasUniqueIndexForColumns( array $columns ) :bool {
		try {
			$grouped = ( new TableIndices( $this->getTableSchema() ) )->retrieveGroupedBy();
		}
		catch ( \Throwable $e ) {
			return false;
		}

		$columns = \array_map( '\strtolower', $columns );
		\sort( $columns );

		foreach ( $grouped as $rows ) {
			$indexedColumns = \array_keys( $rows );
			\sort( $indexedColumns );
			if ( \serialize( $columns ) === \serialize( $indexedColumns ) ) {
				$isUnique = true;
				foreach ( $rows as $row ) {
					$isUnique = $isUnique && (int)( $row[ 'non_unique' ] ?? 1 ) === 0;
				}
				if ( $isUnique ) {
					return true;
				}
			}
		}

		return false;
	}
}
