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
		/** @var AuditTrail\Options $opts */
		$opts = $this->getOptions();

		$this->loadAuditorWriter()->setIfCommit( true );

		if ( $opts->isAuditUsers() ) {
			( new Auditors\Users() )
				->setMod( $this->getMod() )
				->run();
		}
		if ( $opts->isAuditPlugins() ) {
			( new Auditors\Plugins() )
				->setMod( $this->getMod() )
				->run();
		}
		if ( $opts->isAuditThemes() ) {
			( new Auditors\Themes() )
				->setMod( $this->getMod() )
				->run();
		}
		if ( $opts->isAuditWp() ) {
			( new Auditors\Wordpress() )
				->setMod( $this->getMod() )
				->run();
		}
		if ( $opts->isAuditPosts() ) {
			( new Auditors\Posts() )
				->setMod( $this->getMod() )
				->run();
		}
		if ( $opts->isAuditEmails() ) {
			( new Auditors\Emails() )
				->setMod( $this->getMod() )
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