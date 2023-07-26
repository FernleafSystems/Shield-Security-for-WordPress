<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	use ModConsumer;

	protected function run() {
		$this->mod()->getAuditCon()->execute();
		$this->getSubProAuditor()->execute();
	}

	/**
	 * @return \ICWP_WPSF_Processor_AuditTrail_Auditor|mixed
	 */
	public function getSubProAuditor() {
		return new \ICWP_WPSF_Processor_AuditTrail_Auditor( $this->mod() );
	}
}