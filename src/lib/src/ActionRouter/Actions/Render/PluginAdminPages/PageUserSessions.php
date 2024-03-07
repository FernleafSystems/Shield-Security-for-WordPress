<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;

class PageUserSessions extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_user_sessions';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_sessions.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'text'    => __( 'User Controls', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'offcanvas_form_mod_cfg' ],
				'datas'   => [
					'config_item' => EnumModules::USERS,
				],
			],
			[
				'text'    => __( 'Configure Security Admin', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'offcanvas_form_mod_cfg' ],
				'datas'   => [
					'config_item' => EnumModules::SECURITY_ADMIN,
				],
			],
		];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'text'       => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'User Sessions', 'wp-simple-firewall' ) ),
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
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select $metaSelect */
		$metaSelect = self::con()->db_con->dbhUserMeta()->getQuerySelector();
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