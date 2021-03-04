<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Common\AlignTableWithSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Handler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 */
abstract class Handler {

	use ModConsumer;
	use ExecOnce;

	/**
	 * @var string
	 */
	protected $sTable;

	/**
	 * @var bool
	 */
	private $bIsReady;

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var TableSchema
	 */
	protected $schema;

	public function __construct( $slug = '' ) {
		$this->slug = $slug;
	}

	/**
	 * @throws \Exception
	 */
	protected function run() {
		$this->tableInit();
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function tableInit() {

		$this->setupTableSchema();

		if ( !$this->isReady() ) {

			$this->tableCreate();

			if ( !$this->isReady( true ) ) {
				$this->tableDelete();
				$this->tableCreate();
			}
		}
		return $this;
	}

	private function setupTableSchema() :TableSchema {
		$this->schema = new TableSchema();

		$spec = $this->getOptions()->getDef( 'db_table_'.$this->slug );

		if ( empty( $spec ) ) {
			$this->schema->slug = $this->slug;
			$this->schema->primary_key = 'id';
			$this->schema->cols_custom = $this->getCustomColumns();
			$this->schema->cols_timestamps = $this->getTimestampColumns();
			$this->schema->autoexpire = 0;
		}
		else {
			$this->schema->applyFromArray( array_merge(
				[
					'slug'            => $this->slug,
					'primary_key'     => 'id',
					'cols_custom'     => [],
					'cols_timestamps' => [],
					'has_updated_at'  => false,
					'col_older_than'  => 'created_at',
					'autoexpire'      => 0,
					'has_ip_col'      => false,
				],
				$spec
			) );
		}

		$this->schema->table = $this->getTable();
		return $this->schema;
	}

	public function autoCleanDb() {
	}

	public function tableCleanExpired( int $autoExpireDays ) {
		if ( $autoExpireDays > 0 ) {
			$this->deleteRowsOlderThan( Services::Request()->ts() - $autoExpireDays*DAY_IN_SECONDS );
		}
	}

	protected function getColumnForOlderThanComparison() :string {
		return 'created_at';
	}

	/**
	 * @param int $timestamp
	 * @return bool
	 */
	public function deleteRowsOlderThan( $timestamp ) :bool {
		return $this->isReady() &&
			   $this->getQueryDeleter()
					->addWhereOlderThan( $timestamp, $this->getTableSchema()->col_older_than ?? 'created_at' )
					->query();
	}

	public function getTable() :string {
		return Services::WpDb()->getPrefix()
			   .esc_sql( $this->getCon()->prefixOption( $this->getDefaultTableName() ) );
	}

	/**
	 * @return string
	 * @deprecated 10.3
	 */
	protected function getTableSlug() {
		return empty( $this->sTable ) ? $this->getDefaultTableName() : $this->sTable;
	}

	/**
	 * @return Insert|mixed
	 */
	public function getQueryInserter() {
		$sClass = $this->getNamespace().'Insert';
		/** @var Insert $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return Delete|mixed
	 */
	public function getQueryDeleter() {
		$sClass = $this->getNamespace().'Delete';
		/** @var Delete $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return Select|mixed
	 */
	public function getQuerySelector() {
		$sClass = $this->getNamespace().'Select';
		/** @var Select $o */
		$o = new $sClass();
		return $o->setDbH( $this )
				 ->setResultsAsVo( true );
	}

	/**
	 * @return Update|mixed
	 */
	public function getQueryUpdater() {
		$sClass = $this->getNamespace().'Update';
		/** @var Update $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return EntryVO|mixed
	 */
	public function getVo() {
		$sClass = $this->getNamespace().'EntryVO';
		return new $sClass();
	}

	public function hasColumn( string $col ) :bool {
		return in_array( strtolower( $col ), $this->getTableSchema()->getColumnNames() );
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function tableCreate() {
		$DB = Services::WpDb();
		$sch = $this->getTableSchema();
		if ( !$DB->getIfTableExists( $sch->table ) ) {
			$DB->doSql( $sch->buildCreate() );
		}
		return $this;
	}

	public function tableDelete( bool $truncate = false ) :bool {
		$table = $this->getTable();
		$DB = Services::WpDb();
		$mResult = !$this->tableExists() ||
				   ( $truncate ? $DB->doTruncateTable( $table ) : $DB->doDropTable( $table ) );
		$this->reset();
		return $mResult !== false;
	}

	public function tableExists() :bool {
		return Services::WpDb()->getIfTableExists( $this->getTable() );
	}

	public function tableTrimExcess( int $nRowsLimit ) :self {
		try {
			$this->getQueryDeleter()->deleteExcess( $nRowsLimit );
		}
		catch ( \Exception $e ) {
		}
		return $this;
	}

	/**
	 * @param bool $reTest
	 * @return bool
	 */
	public function isReady( bool $reTest = false ) {
		if ( $reTest ) {
			$this->reset();
		}

		if ( !isset( $this->bIsReady ) ) {
			try {
				$align = new AlignTableWithSchema( $this->getTableSchema() );
				$align->align();
				$this->bIsReady = $this->tableExists() && $align->isAligned();
			}
			catch ( \Exception $e ) {
				$this->bIsReady = false;
			}
		}

		return $this->bIsReady;
	}

	protected function getDefaultTableName() :string {
		return $this->getTableSchema()->slug;
	}

	/**
	 * @return string[]
	 * @deprecated 10.3
	 */
	protected function getCustomColumns() :array {
		return [];
	}

	/**
	 * @return string[]
	 * @deprecated 10.3
	 */
	protected function getTimestampColumns() :array {
		return [];
	}

	public function getTableSchema() :TableSchema {
		if ( empty( $this->schema ) ) { // TODO: Delete empty test after 10.3
			$sch = new TableSchema();
			$sch->table = $this->getTable();
			$sch->col_older_than = 'created_at';
			$sch->cols_custom = $this->getCustomColumns();
			$sch->cols_timestamps = $this->getTimestampColumns();
			return $sch;
		}
		return $this->schema;
	}

	private function getNamespace() :string {
		try {
			$namespace = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $e ) {
			$namespace = __NAMESPACE__;
		}
		return rtrim( $namespace, '\\' ).'\\';
	}

	/**
	 * @return $this
	 */
	private function reset() {
		unset( $this->bIsReady );
		return $this;
	}
}