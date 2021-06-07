<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	/**
	 * @var Lib\AuditWriter
	 */
	private $auditWriter;

	protected function run() {
		$this->initAuditors();
		$this->getSubProAuditor()->execute();
	}

	/**
	 * @return Lib\AuditWriter
	 */
	private function loadAuditorWriter() :Lib\AuditWriter {
		if ( !isset( $this->auditWriter ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$this->auditWriter = ( new Lib\AuditWriter( $this->getCon() ) )
				->setDbHandler( $mod->getDbHandler_AuditTrail() );
		}
		return $this->auditWriter;
	}

	private function initAuditors() {
		$this->loadAuditorWriter()->setIfCommit( true );
		foreach ( $this->getAuditors() as $auditor ) {
			$auditor->setMod( $this->getMod() )->execute();
		}
	}

	/**
	 * @return Base[]
	 */
	private function getAuditors() :array {
		return [
			new Auditors\Users(),
			new Auditors\Plugins(),
			new Auditors\Themes(),
			new Auditors\Wordpress(),
			new Auditors\Posts(),
			new Auditors\Emails(),
			new Auditors\Upgrades(),
		];
	}

	/**
	 * @return \ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return new \ICWP_WPSF_Processor_AuditTrail_Auditor( $this->getMod() );
	}
}