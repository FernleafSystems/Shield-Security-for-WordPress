<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

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

	/**
	 * @var string
	 */
	protected $sTable;

	/**
	 * @var bool
	 */
	private $bIsReady;

	public function __construct() {
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
					->addWhereOlderThan( $timestamp, $this->getColumnForOlderThanComparison() )
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

	/**
	 * @return bool
	 */
	public function tableExists() {
		return Services::WpDb()->getIfTableExists( $this->getTable() );
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function tableInit() {
		if ( !$this->isReady() ) {

			$this->tableCreate();

			if ( !$this->isReady( true ) ) {
				$this->tableDelete();
				$this->tableCreate();
			}
		}
		return $this;
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
	 * @param bool $bReTest
	 * @return bool
	 */
	public function isReady( $bReTest = false ) {
		if ( $bReTest ) {
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
		throw new \Exception( 'No table name' );
	}

	/**
	 * @return string[]
	 */
	protected function getCustomColumns() :array {
		return [];
	}

	/**
	 * @return string[]
	 */
	protected function getTimestampColumns() :array {
		return [];
	}

	public function getTableSchema() :TableSchema {
		$sch = new TableSchema();
		$sch->table = $this->getTable();
		$sch->cols_custom = $this->getCustomColumns();
		$sch->cols_timestamps = $this->getTimestampColumns();
		return $sch;
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