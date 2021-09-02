<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\AuditTrail\ForAuditTrail;

class UI extends BaseShield\UI {

	public function renderAuditTrailTable() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Databases\AuditTrail\Select $dbSel */
		$dbSel = $mod->getDbHandler_AuditTrail()->getQuerySelector();

		$eventsSelect = array_intersect_key(
			$con->loadEventsService()->getEventNames(),
			array_flip( $dbSel->getDistinctEvents() )
		);
		asort( $eventsSelect );

		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/audit/audit_table.twig',
						[
							'ajax'    => [
								'logtable_action' => $mod->getAjaxActionData( 'logtable_action', true ),
							],
							'flags'   => [],
							'strings' => [
								'table_title'             => sprintf( '%s: %s', __( 'Logs', 'wp-simple-firewall' ), __( 'Audit Trail', 'wp-simple-firewall' ) ),
							],
							'vars'    => [
								'datatables_init'   => ( new ForAuditTrail() )
									->setMod( $this->getMod() )
									->build()
							],
						],
						true
					);
	}
}