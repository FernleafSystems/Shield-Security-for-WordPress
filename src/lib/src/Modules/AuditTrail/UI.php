<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\AuditTrail\ForAuditTrail;

class UI extends BaseShield\UI {

	public function renderAuditTrailTable() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->renderTemplate(
			'/wpadmin_pages/insights/audit_trail/audit_table.twig',
			[
				'ajax'    => [
					'logtable_action' => $mod->getAjaxActionData( 'logtable_action', true ),
				],
				'flags'   => [],
				'strings' => [
					'table_title' => __( 'Activity Log', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'datatables_init' => ( new ForAuditTrail() )
						->setMod( $this->getMod() )
						->build()
				],
			],
			true
		);
	}
}