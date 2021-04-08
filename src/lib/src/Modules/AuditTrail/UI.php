<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class UI extends BaseShield\UI {

	public function renderAuditTrailTable() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Databases\AuditTrail\Select $dbSel */
		$dbSel = $mod->getDbHandler_AuditTrail()->getQuerySelector();

		/** @var Modules\Events\Strings $oEventStrings */
		$oEventStrings = $con->getModule_Events()->getStrings();
		$aEventsSelect = array_intersect_key( $oEventStrings->getEventNames(), array_flip( $dbSel->getDistinctEvents() ) );
		asort( $aEventsSelect );

		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/audit/audit_table.twig',
						[
							'ajax'    => [
								'render_table_audittrail' => $mod->getAjaxActionData( 'render_table_audittrail', true ),
								'item_addparamwhite'      => $mod->getAjaxActionData( 'item_addparamwhite', true )
							],
							'flags'   => [],
							'strings' => [
								'table_title'             => sprintf( '%s: %s', __( 'Logs', 'wp-simple-firewall' ), __( 'Audit Trail', 'wp-simple-firewall' ) ),
								'sub_title'               => __( 'Use the Audit Trail Glossary for help interpreting log entries.', 'wp-simple-firewall' ),
								'title_filter_form'       => __( 'Audit Trail Filters', 'wp-simple-firewall' ),
								'username_ignores'        => __( "Providing a username will cause the 'logged-in' filter to be ignored.", 'wp-simple-firewall' ),
								'exclude_your_ip'         => __( 'Exclude Your Current IP', 'wp-simple-firewall' ),
								'exclude_your_ip_tooltip' => __( 'Exclude Your IP From Results', 'wp-simple-firewall' ),
								'context'                 => __( 'Context', 'wp-simple-firewall' ),
								'event'                   => __( 'Event', 'wp-simple-firewall' ),
								'show_after'              => __( 'show results that occurred after', 'wp-simple-firewall' ),
								'show_before'             => __( 'show results that occurred before', 'wp-simple-firewall' ),
							],
							'vars'    => [
								'events_for_select' => $aEventsSelect,
								'unique_ips'        => $dbSel->getDistinctIps(),
								'unique_users'      => $dbSel->getDistinctUsernames(),
							],
						],
						true
					);
	}
}