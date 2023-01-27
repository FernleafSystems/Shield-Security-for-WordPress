<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\UserSessionDelete;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\UserSessionsTableBulkAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\UserSessionsTableRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModCon;

class PageUserSessions extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_user_sessions';
	public const PRIMARY_MOD = 'user_management';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/table_sessions.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'ajax'    => [
				'render_table_sessions' => ActionData::BuildJson( UserSessionsTableRender::SLUG ),
				'item_delete'           => ActionData::BuildJson( UserSessionDelete::SLUG ),
				'bulk_action'           => ActionData::BuildJson( UserSessionsTableBulkAction::SLUG ),

			],
			'hrefs'   => [
				'inner_page_config' => [
					[
						'text' => __( 'Config', 'wp-simple-firewall' ),
					],
					[
						'text' => __( 'User Controls', 'wp-simple-firewall' ),
						'href' => $con->plugin_urls->offCanvasConfigRender( $this->primary_mod->getSlug() ),
					],
					[
						'text' => __( 'Security Admin', 'wp-simple-firewall' ),
						'href' => $con->plugin_urls->offCanvasConfigRender( $con->getModule_SecAdmin()->getSlug() ),
					],
				],
			],
			'strings' => [
				'title_filter_form'   => __( 'Sessions Table Filters', 'wp-simple-firewall' ),
				'users_title'         => __( 'User Sessions', 'wp-simple-firewall' ),
				'users_subtitle'      => __( 'Review and manage current user sessions', 'wp-simple-firewall' ),
				'users_maybe_expired' => __( "Some sessions may have expired but haven't been automatically cleaned from the database yet", 'wp-simple-firewall' ),
				'username'            => __( 'Username', 'wp-simple-firewall' ),
				'inner_page_title'    => __( 'User Sessions', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and manage details of current user sessions on the site.', 'wp-simple-firewall' ),
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