<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;

class ICWP_WPSF_Processor_AuditTrail_Auditor extends ShieldProcessor {

	/**
	 * @CENTRAL
	 * @param string $sContext
	 * @param string $sOrderBy
	 * @param string $sOrder
	 * @param int    $nPage
	 * @param int    $nLimit
	 * @return AuditTrail\EntryVO[]
	 */
	public function getAuditEntriesForContext( $sContext = 'all', $sOrderBy = 'created_at', $sOrder = 'DESC', $nPage = 1, $nLimit = 50 ) {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();
		$oSelect = $oMod->getDbHandler_AuditTrail()
						->getQuerySelector()
						->setOrderBy( $sOrderBy, $sOrder )
						->setLimit( $nLimit )
						->setPage( $nPage );
		return $oSelect->query();
	}
}