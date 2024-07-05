<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules\{
	ModuleSecadmin,
	ModuleUsers
};

class PageUserSessions extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_user_sessions';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_sessions.twig';

	protected function getPageContextualHrefs() :array {
		$zones = self::con()->comps->zones;
		$cfgUsers = $zones->getZoneComponent( ModuleUsers::Slug() )->getActions()[ 'config' ];
		$cfgUsers[ 'title' ] = __( 'User Controls', 'wp-simple-firewall' );
		$cfgSecAdmin = $zones->getZoneComponent( ModuleSecadmin::Slug() )->getActions()[ 'config' ];
		$cfgSecAdmin[ 'title' ] = __( 'Configure Security Admin', 'wp-simple-firewall' );
		return [
			$cfgUsers,
			$cfgSecAdmin,
		];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'User Sessions', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/434-user-sessions-management-tool',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		return [
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'person-badge' ),
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
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\Ops\Select $metaSelect */
		$metaSelect = self::con()->db_con->user_meta->getQuerySelector();
		$results = $metaSelect->setResultsAsVo( false )
							  ->setSelectResultsFormat( ARRAY_A )
							  ->setColumnsToSelect( [ 'user_id' ] )
							  ->setOrderBy( 'updated_at' )
							  ->setLimit( 20 )
							  ->queryWithResult();

		$results = \array_map(
			function ( $object ) {
				return $object->user_login;
			},
			( new \WP_User_Query( [
				'fields'  => [ 'user_login' ],
				'include' => \array_map(
					function ( $res ) {
						return (int)$res[ 'user_id' ];
					},
					\is_array( $results ) ? $results : []
				)
			] ) )->get_results()
		);
		\asort( $results );
		return $results;
	}
}