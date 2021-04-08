<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class UI extends BaseShield\UI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $dbSel */
		$dbSel = $con->getModule_Sessions()
					 ->getDbHandler_Sessions()
					 ->getQuerySelector();

		return [
			'ajax'    => [
				'render_table_sessions' => $mod->getAjaxActionData( 'render_table_sessions', true ),
				'item_delete'           => $mod->getAjaxActionData( 'session_delete', true ),
				'bulk_action'           => $mod->getAjaxActionData( 'bulk_action', true ),

			],
			'flags'   => [],
			'strings' => [
				'title_filter_form'   => __( 'Sessions Table Filters', 'wp-simple-firewall' ),
				'users_title'         => __( 'User Sessions', 'wp-simple-firewall' ),
				'users_subtitle'      => __( 'Review and manage current user sessions', 'wp-simple-firewall' ),
				'users_maybe_expired' => __( "Some sessions may have expired but haven't been automatically cleaned from the database yet", 'wp-simple-firewall' ),
				'username'            => __( 'Username', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'unique_ips'    => $dbSel->getDistinctIps(),
				'unique_users'  => $dbSel->getDistinctUsernames(),
			],
		];
	}
}