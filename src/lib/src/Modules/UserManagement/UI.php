<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class UI extends BaseShield\UI {

	public function buildInsightsVars() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
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
				'unique_users' => $this->getDistinctUsernames(),
			],
		];
	}

	private function getDistinctUsernames() :array {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select $metaSelect */
		$metaSelect = $this->getCon()
						   ->getModule_Data()
						   ->getDbH_UserMeta()
						   ->getQuerySelector();
		$results = $metaSelect->setResultsAsVo( false )
							  ->setSelectResultsFormat( ARRAY_A )
							  ->setColumnsToSelect( [ 'user_id' ] )
							  ->setOrderBy( 'updated_at', 'DESC' )
							  ->setLimit( 20 )
							  ->queryWithResult();

		$results = array_map(
			function ( $object ) {
				return $object->user_login;
			},
			( new \WP_User_Query( [
				'fields'  => [ 'user_login' ],
				'include' => array_map(
					function ( $res ) {
						return (int)$res[ 'user_id' ];
					},
					is_array( $results ) ? $results : []
				)
			] ) )->get_results()
		);
		asort( $results );
		return $results;
	}
}