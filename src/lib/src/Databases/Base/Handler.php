<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BaseHandler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 */
class Handler {

	/**
	 * The defined table columns.
	 * @var array
	 */
	protected $aColDef;

	/**
	 * The actual table columns.
	 * @var array
	 */
	protected $aColActual;

	/**
	 * @var string
	 */
	protected $sTable;

	/**
	 * @var string
	 */
	protected $sNamespace;

	/**
	 * @var bool
	 */
	private $bTableExist;

	/**
	 * @var string
	 */
	private $sSqlCreate;

	public function __construct() {
	}

	/**
	 * @param int $nTimeStamp
	 * @return bool
	 */
	public function deleteRowsOlderThan( $nTimeStamp ) {
		return $this->getQueryDeleter()
					->addWhereOlderThan( $nTimeStamp )
					->query();
	}

	/**
	 * @return bool
	 */
	public function deleteTable() {
		return $this->isTable() ? Services::WpDb()->doDropTable( $this->getTable() ) : false;
	}

	/**
	 * @return string[]
	 */
	public function getColumnsActual() {
		if ( empty( $this->aColActual ) ) {
			$this->aColActual = Services::WpDb()->getColumnsForTable( $this->getTable(), 'strtolower' );
		}
		return is_array( $this->aColActual ) ? $this->aColActual : array();
	}

	/**
	 * @return string[]
	 */
	public function getColumnsDefinition() {
		return is_array( $this->aColDef ) ? $this->aColDef : array();
	}

	/**
	 * @return string
	 */
	public function getTable() {
		return $this->sTable;
	}

	/**
	 * @return Insert
	 */
	public function getQueryInserter() {
		$sClass = $this->getNamespace().'\\Insert';
		/** @var Insert $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return Delete
	 */
	public function getQueryDeleter() {
		$sClass = $this->getNamespace().'\\Delete';
		/** @var Delete $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return Select
	 */
	public function getQuerySelector() {
		$sClass = $this->getNamespace().'\\Select';
		/** @var Select $o */
		$o = new $sClass();
		return $o->setDbH( $this )
				 ->setResultsAsVo( true );
	}

	/**
	 * @return Update
	 */
	public function getQueryUpdater() {
		$sClass = $this->getNamespace().'\\Update';
		/** @var Update $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return EntryVO
	 */
	public function getVo() {
		$sClass = $this->getNamespace().'\\EntryVO';
		return new $sClass();
	}

	/**
	 * @return string
	 */
	public function getSqlCreate() {
		return $this->sSqlCreate;
	}

	/**
	 * @param string $sCol
	 * @return bool
	 */
	public function hasColumn( $sCol ) {
		return in_array( strtolower( $sCol ), array_map( 'strtolower', $this->getColumnsActual() ) );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function tableInit() {

		$bSuccess = $this->isReady();

		if ( !$bSuccess ) {

			// apply DB Delta
			if ( $this->isTable() ) {
				$this->tableCreate();
			}

			if ( !$this->isReady( true ) ) {
				$this->deleteTable();
				$this->tableCreate();
			}

			$bSuccess = $this->isReady( true );
		}
		return $bSuccess;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function tableCreate() {
		$sSql = $this->getSqlCreate();
		if ( empty( $sSql ) ) {
			throw new \Exception( 'Table Create SQL is empty' );
		}
		$sSql = sprintf( $sSql, $this->getTable(), Services::WpDb()->getCharCollate() );
		Services::WpDb()->dbDelta( $sSql );
		return $this;
	}

	/**
	 * @param bool $bReTest
	 * @return bool
	 * @throws \Exception
	 */
	public function isReady( $bReTest = false ) {
		if ( $bReTest ) {
			unset( $this->bTableExist );
			unset( $this->aColActual );
		}
		return $this->isTable() && $this->verifyTableStructure();
	}

	/**
	 * @return bool
	 */
	public function isTable() {
		if ( !isset( $this->bTableExist ) ) {
			$this->bTableExist = Services::WpDb()->getIfTableExists( $this->getTable() );
		}
		return $this->bTableExist;
	}

	/**
	 * @param string[] $aColumns
	 * @return $this
	 */
	public function setColumnsDefinition( $aColumns ) {
		$this->aColDef = $aColumns;
		return $this;
	}

	/**
	 * @param string $sSqlCreate
	 * @return $this
	 */
	public function setSqlCreate( $sSqlCreate ) {
		$this->sSqlCreate = $sSqlCreate;
		return $this;
	}

	/**
	 * @param string $sTable
	 * @return $this
	 */
	public function setTable( $sTable ) {
		$this->sTable = Services::WpDb()->getPrefix().esc_sql( $sTable );
		return $this;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function verifyTableStructure() {
		$aColDef = array_map( 'strtolower', $this->getColumnsDefinition() );
		if ( empty( $aColDef ) ) {
			throw new \Exception( 'Could not verify table structure as no columns definition provided' );
		}

		$aColActual = $this->getColumnsActual();
		return ( count( array_diff( $aColActual, $aColDef ) ) <= 0
				 && ( count( array_diff( $aColDef, $aColActual ) ) <= 0 ) );
	}

	/**
	 * @return string
	 */
	private function getNamespace() {
		try {
			$sName = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $oE ) {
			$sName = __NAMESPACE__;
		}
		return $sName;
	}
}