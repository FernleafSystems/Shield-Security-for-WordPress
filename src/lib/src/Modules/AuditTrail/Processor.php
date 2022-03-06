<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		$this->initAuditors();
		$this->getSubProAuditor()->execute();
	}

	private function initAuditors() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getAuditLogger()->setIfCommit( true );
		foreach ( $this->getAuditors() as $auditorClass ) {
			/** @var Auditors\Base $auditor */
			$auditor = new $auditorClass();
			$auditor->setMod( $this->getMod() )->execute();
		}
	}

	private function getAuditors() :array {
		return [
			Auditors\Users::class,
			Auditors\Plugins::class,
			Auditors\Themes::class,
			Auditors\Wordpress::class,
			Auditors\Posts::class,
			Auditors\Emails::class,
			Auditors\Upgrades::class
		];
	}

	/**
	 * @return \ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return new \ICWP_WPSF_Processor_AuditTrail_Auditor( $this->getMod() );
	}
}