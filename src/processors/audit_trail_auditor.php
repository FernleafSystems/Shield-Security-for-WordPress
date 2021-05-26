<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Processor;

class ICWP_WPSF_Processor_AuditTrail_Auditor extends Processor {

	/**
	 * @CENTRAL
	 * @param string $context
	 * @param string $orderBy
	 * @param string $order
	 * @param int    $page
	 * @param int    $limit
	 * @return AuditTrail\EntryVO[]
	 */
	public function getAuditEntriesForContext( $context = 'all', $orderBy = 'created_at', $order = 'DESC', $page = 1, $limit = 50 ) {
		/** @var Shield\Modules\AuditTrail\ModCon $mod */
		$mod = $this->getMod();
		$select = $mod->getDbHandler_AuditTrail()
					  ->getQuerySelector()
					  ->setOrderBy( $orderBy, $order )
					  ->setLimit( $limit )
					  ->setPage( $page );
		return $select->query();
	}
}