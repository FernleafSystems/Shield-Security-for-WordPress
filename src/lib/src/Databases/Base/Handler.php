<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Common\AlignTableWithSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class Handler extends ExecOnceModConsumer {

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
			$this->getOptions()->getDef( 'db_table_'.$this->slug )
		) );

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
			   .esc_sql( $this->getCon()->prefixOption( $this->getTableSchema()->slug ) );
	}

	/**
	 * @return Insert|mixed
	 */
	public function getQueryInserter() {
		$class = $this->getNamespace().'Insert';
		/** @var Insert $o */
		$o = new $class();
		return $o->setDbH( $this );
	}

	/**
	 * @return Iterator
	 */
	public function getIterator() {
		$o = new Iterator();
		return $o->setDbHandler( $this );
	}

	/**
	 * @return Delete|mixed
	 */
	public function getQueryDeleter() {
		$class = $this->getNamespace().'Delete';
		/** @var Delete $o */
		$o = new $class();
		return $o->setDbH( $this );
	}

	/**
	 * @return Select|mixed
	 */
	public function getQuerySelector() {
		$class = $this->getNamespace().'Select';
		/** @var Select $o */
		$o = new $class();
		return $o->setDbH( $this )
				 ->setResultsAsVo( true );
	}

	/**
	 * @return Update|mixed
	 */
	public function getQueryUpdater() {
		$class = $this->getNamespace().'Update';
		/** @var Update $o */
		$o = new $class();
		return $o->setDbH( $this );
	}

	/**
	 * @return EntryVO|mixed
	 */
	public function getVo() {
		$class = $this->getNamespace().'EntryVO';
		return new $class();
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

	public function tableTrimExcess( int $rowsLimit ) :self {
		try {
			$this->getQueryDeleter()->deleteExcess( $rowsLimit );
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

	public function getTableSchema() :TableSchema {
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