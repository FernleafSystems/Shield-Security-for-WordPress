<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		/** @var Select $dbSel */
		$dbSel = $this->getCon()
					  ->getModule_Sessions()
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
				'unique_ips'   => $dbSel->getDistinctIps(),
				'unique_users' => $dbSel->getDistinctUsernames(),
			],
		];
	}

	/**
	 * @return array
	 */
	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$notices = [
			'title'    => __( 'Users', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //admin user
			$oAdmin = Services::WpUsers()->getUserByUsername( 'admin' );
			if ( !empty( $oAdmin ) && user_can( $oAdmin, 'manage_options' ) ) {
				$notices[ 'messages' ][ 'admin' ] = [
					'title'   => 'Admin User',
					'message' => sprintf( __( "Default 'admin' user still available.", 'wp-simple-firewall' ) ),
					'href'    => '',
					'rec'     => __( "Default 'admin' user should be disabled or removed.", 'wp-simple-firewall' )
				];
			}
		}

		{//password policies
			if ( !$opts->isPasswordPoliciesEnabled() ) {
				$notices[ 'messages' ][ 'password' ] = [
					'title'   => __( 'Password Policies', 'wp-simple-firewall' ),
					'message' => __( "Strong password policies are not enforced.", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_passwords' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Password policies should be turned-on.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}
}