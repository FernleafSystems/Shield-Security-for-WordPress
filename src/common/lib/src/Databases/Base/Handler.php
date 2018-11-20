<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class BaseHandler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 */
class Handler {

	/**
	 * @var array
	 */
	protected $aColumnsDefinition;

	/**
	 * @var string
	 */
	protected $sTable;

	/**
	 * @var string
	 */
	protected $sNamespace;

	public function __construct() {
	}

	/**
	 * @return string[]
	 */
	public function getColumnsDefinition() {
		return is_array( $this->aColumnsDefinition ) ? $this->aColumnsDefinition : array();
	}

	/**
	 * @return string
	 */
	protected function getNameSpace() {
		try {
			$sName = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $oE ) {
			$sName = __NAMESPACE__;
		}
		return $sName;
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
		$sClass = $this->getNameSpace().'\\Insert';
		/** @var Insert $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return Delete
	 */
	public function getQueryDeleter() {
		$sClass = $this->getNameSpace().'\\Delete';
		/** @var Delete $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return Select
	 */
	public function getQuerySelector() {
		$sClass = $this->getNameSpace().'\\Select';
		/** @var Select $o */
		$o = new $sClass();
		return $o->setDbH( $this )
				 ->setResultsAsVo( true );
	}

	/**
	 * @return Update
	 */
	public function getQueryUpdater() {
		$sClass = $this->getNameSpace().'\\Update';
		/** @var Update $o */
		$o = new $sClass();
		return $o->setDbH( $this );
	}

	/**
	 * @return EntryVO
	 */
	public function getVo() {
		$sClass = $this->getNameSpace().'\\EntryVO';
		/** @var EntryVO $o */
		$o = new $sClass();
		return $o;
	}

	/**
	 * @param string[] $aColumns
	 * @return $this
	 */
	public function setColumnsDefinition( $aColumns ) {
		$this->aColumnsDefinition = $aColumns;
		return $this;
	}

	/**
	 * @param string $sTable
	 * @return $this
	 */
	public function setTable( $sTable ) {
		$this->sTable = $sTable;
		return $this;
	}
}