<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditWriter;

class ICWP_WPSF_Processor_AuditTrail extends Modules\BaseShield\ShieldProcessor {

	/**
	 * @var AuditWriter
	 */
	private $oAuditor;

	public function run() {
		$this->initAuditors();
		$this->getSubProAuditor()->execute();
	}

	/**
	 * @return AuditWriter
	 */
	private function loadAuditorWriter() {
		if ( !isset( $this->oAuditor ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $mod */
			$mod = $this->getMod();
			$this->oAuditor = ( new AuditWriter( $this->getCon() ) )
				->setDbHandler( $mod->getDbHandler_AuditTrail() );
		}
		return $this->oAuditor;
	}

	private function initAuditors() {
		$this->loadAuditorWriter()->setIfCommit( true );

		( new Auditors\Users() )
			->setMod( $this->getMod() )
			->run();
		( new Auditors\Plugins() )
			->setMod( $this->getMod() )
			->run();
		( new Auditors\Themes() )
			->setMod( $this->getMod() )
			->run();
		( new Auditors\Wordpress() )
			->setMod( $this->getMod() )
			->run();
		( new Auditors\Posts() )
			->setMod( $this->getMod() )
			->run();
		( new Auditors\Emails() )
			->setMod( $this->getMod() )
			->run();
		( new Auditors\Upgrades() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return $this->getSubPro( 'auditor' );
	}

	protected function getSubProMap() :array {
		return [
			'auditor' => 'ICWP_WPSF_Processor_AuditTrail_Auditor',
		];
	}
}