<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForTraffic extends Base {

	protected function getOrderColumnSlug() :string {
		return 'date';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'ip',
			'user',
			'page',
			'details',
			'response',
			'date',
			'type',
			'path',
			'code',
			'offense',
			'country',
			'day',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'      => [
				'data'        => 'rid',
				'title'       => __( 'Request ID', 'wp-simple-firewall' ),
				'className'   => 'rid',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => false,
				],
			],
			'page'     => [
				'data'        => 'page',
				'title'       => __( 'Page', 'wp-simple-firewall' ),
				'className'   => 'page',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'details'  => [
				'data'        => 'details',
				'title'       => __( 'Details', 'wp-simple-firewall' ),
				'className'   => 'details',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'response' => [
				'data'        => 'response',
				'title'       => __( 'Response', 'wp-simple-firewall' ),
				'className'   => 'response',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'type'     => [
				'data'        => 'type',
				'title'       => __( 'Type', 'wp-simple-firewall' ),
				'className'   => 'type',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => true,
				],
			],
			'ip'       => [
				'data'        => 'ip',
				'title'       => __( 'IP Address', 'wp-simple-firewall' ),
				'className'   => 'ip',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => false,
				],
			],
			'code'     => [
				'data'        => 'code',
				'title'       => __( 'Response Code', 'wp-simple-firewall' ),
				'className'   => 'code',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => true,
				],
			],
			'country'  => [
				'data'        => 'country',
				'title'       => __( 'Country', 'wp-simple-firewall' ),
				'className'   => 'country',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => false,
				],
			],
			'offense'  => [
				'data'        => 'offense',
				'title'       => __( 'Is Offense', 'wp-simple-firewall' ),
				'className'   => 'offense',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => true,
				],
			],
			'path'     => [
				'data'        => 'path',
				'title'       => __( 'Path', 'wp-simple-firewall' ),
				'className'   => 'path',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => false,
				],
			],
			'uid'      => [
				'data'       => 'uid',
				'title'      => __( 'User ID', 'wp-simple-firewall' ),
				'className'  => 'uid',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => false,
			],
			'user'     => [
				'data'           => 'user',
				'title'          => __( 'User', 'wp-simple-firewall' ),
				'className'      => 'user',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'day'      => [
				'data'          => 'day',
				'title'         => __( 'Day', 'wp-simple-firewall' ),
				'className'     => 'day',
				'orderable'     => false,
				'orderSequence' => [ 'desc' ],
				'searchable'    => false,
				'visible'       => false,
				'searchPanes'   => [
					'show' => true,
				],
			],
			'date'     => [
				'data'          => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'         => __( 'Date', 'wp-simple-firewall' ),
				'className'     => 'date',
				'orderable'     => true,
				'orderSequence' => [ 'desc', 'asc' ],
				'searchable'    => false,
				'visible'       => true,
				'searchPanes'   => [
					'show' => false
				],
			],
		];
	}
}