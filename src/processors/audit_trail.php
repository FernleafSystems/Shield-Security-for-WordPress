<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditWriter;

class ICWP_WPSF_Processor_AuditTrail extends Modules\BaseShield\ShieldProcessor {

	/**
	 * @var AuditWriter
	 */
	private $oAuditor;

	public function run() {
		/** @var AuditTrail\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isEnabledAuditing() ) {
			$this->initAuditors();
			$this->getSubProAuditor()->execute();
		}
		if ( false && $oOpts->isEnabledChangeTracking() ) {
			$this->getSubProChangeTracking()->execute();
		}
	}

	/**
	 * @return AuditWriter
	 */
	private function loadAuditorWriter() {
		if ( !isset( $this->oAuditor ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
			$oMod = $this->getMod();
			$this->oAuditor = ( new AuditWriter( $this->getCon() ) )
				->setDbHandler( $oMod->getDbHandler_AuditTrail() );
		}
		return $this->oAuditor;
	}

	private function initAuditors() {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();
		/** @var AuditTrail\Options $oOpts */
		$oOpts = $oMod->getOptions();

		$this->loadAuditorWriter()->setIfCommit( true );

		if ( $oOpts->isAuditUsers() ) {
			( new Auditors\Users() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditPlugins() ) {
			( new Auditors\Plugins() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditThemes() ) {
			( new Auditors\Themes() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditWp() ) {
			( new Auditors\Wordpress() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditPosts() ) {
			( new Auditors\Posts() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditEmails() ) {
			( new Auditors\Emails() )
				->setMod( $oMod )
				->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return $this->getSubPro( 'auditor' );
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail_ChangeTracking|mixed
	 */
	public function getSubProChangeTracking() {
		return $this->getSubPro( 'changetracking' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'auditor'        => 'ICWP_WPSF_Processor_AuditTrail_Auditor',
			'changetracking' => 'ICWP_WPSF_Processor_AuditTrail_ChangeTracking',
		];
	}
}