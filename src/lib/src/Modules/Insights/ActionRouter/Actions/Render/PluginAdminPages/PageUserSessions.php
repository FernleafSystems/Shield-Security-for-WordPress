<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\UserSessionDelete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\UserSessionsTableBulkAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\UserSessionsTableRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModCon;

class PageUserSessions extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_user_sessions';
	const PRIMARY_MOD = 'user_management';
	const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/table_sessions.twig';

	protected function getRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		return [
			'ajax'    => [
				'render_table_sessions' => ActionData::BuildJson( UserSessionsTableRender::SLUG ),
				'item_delete'           => ActionData::BuildJson( UserSessionDelete::SLUG ),
				'bulk_action'           => ActionData::BuildJson( UserSessionsTableBulkAction::SLUG ),

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