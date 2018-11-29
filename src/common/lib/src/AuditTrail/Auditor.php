<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO;

class Auditor {

	/**
	 * @var EntryVO[]
	 */
	private $aAudits;

	/**
	 * @param EntryVO $oEntry
	 * @return $this
	 */
	public function addAudit( $oEntry ) {
		$aA = $this->getAudits();
		$aA[] = $oEntry;
		return $this->setAudits( $aA );
	}

	/**
	 * @return EntryVO[]
	 */
	public function getAudits() {
		return is_array( $this->aAudits ) ? $this->aAudits : array();
	}

	/**
	 * @return EntryVO|null
	 */
	public function getLastAudit() {
		$aA = $this->getAudits();
		return array_pop( $aA );
	}

	/**
	 * @return EntryVO
	 */
	public function newAudit() {
		$oNew = new EntryVO();
		$this->addAudit( $oNew );
		return $oNew;
	}

	/**
	 * @param EntryVO[] $aAudits
	 * @return $this
	 */
	protected function setAudits( $aAudits ) {
		$this->aAudits = $aAudits;
		return $this;
	}
}