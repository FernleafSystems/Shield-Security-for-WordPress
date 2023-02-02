<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	SecurityAdminAuthClear,
	UserSessionDelete,
	UserSessionsTableBulkAction,
	UserSessionsTableRender
};

class PageUserSessions extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_user_sessions';
	public const PRIMARY_MOD = 'user_management';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/table_sessions.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->getCon();
		$urls = $this->getCon()->plugin_urls;
		$hrefs = [
			[
				'text' => __( 'User Controls', 'wp-simple-firewall' ),
				'href' => $urls->offCanvasConfigRender( $this->primary_mod->cfg->slug ),
			],
			[
				'text' => __( 'Configure Security Admin', 'wp-simple-firewall' ),
				'href' => $urls->offCanvasConfigRender( $con->getModule_SecAdmin()->cfg->slug ),
			],
		];
		if ( $con->isPluginAdmin() && $con->getModule_SecAdmin()->getSecurityAdminController()->isEnabledSecAdmin() ) {
			$hrefs[] = [
				'text' => __( 'Clear Security Admin Status', 'wp-simple-firewall' ),
				'href' => $urls->noncedPluginAction( SecurityAdminAuthClear::SLUG, $urls->adminHome() ),
			];
		}
		return $hrefs;
	}

	protected function getRenderData() :array {
		return [
			'ajax'    => [
				'render_table_sessions' => ActionData::BuildJson( UserSessionsTableRender::SLUG ),
				'item_delete'           => ActionData::BuildJson( UserSessionDelete::SLUG ),
				'bulk_action'           => ActionData::BuildJson( UserSessionsTableBulkAction::SLUG ),

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
							  ->setOrderBy( 'updated_at' )
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