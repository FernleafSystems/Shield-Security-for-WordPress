<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForSessions extends Base {

	protected function getOrderColumnSlug() :string {
		return 'last_activity_at';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'uid',
			'details',
			'is_secadmin',
			'last_activity_at',
			'logged_in_at',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'              => [
				'data'           => 'rid',
				'title'          => __( 'ID' ),
				'className'      => 'rid',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'uid'              => [
				'data'           => 'uid',
				'title'          => __( 'User' ),
				'className'      => 'uid',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true,
				],
			],
			'details'          => [
				'data'        => 'details',
				'title'       => __( 'Details' ),
				'className'   => 'details',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'is_secadmin'      => [
				'data'        => 'is_secadmin',
				'title'       => __( 'Security Admin', 'wp-simple-firewall' ),
				'className'   => 'is_secadmin',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'last_activity_at' => [
				'data'        => 'last_activity_at',
				'title'       => __( 'Last Activity' ),
				'className'   => 'last_activity_at',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
			'logged_in_at'     => [
				'data'        => 'logged_in_at',
				'title'       => __( 'Logged-In' ),
				'className'   => 'logged_in_at',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
		];
	}
}