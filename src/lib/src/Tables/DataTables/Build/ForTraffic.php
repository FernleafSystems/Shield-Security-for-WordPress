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
				'title'       => __( 'Request ID' ),
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
				'title'       => __( 'Page' ),
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
				'title'       => __( 'Details' ),
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
				'title'       => __( 'Response' ),
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
				'title'       => __( 'Type' ),
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
				'title'       => __( 'IP Address' ),
				'className'   => 'ip',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => true,
				],
			],
			'code'     => [
				'data'        => 'code',
				'title'       => __( 'Response Code' ),
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
				'title'       => __( 'Country' ),
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
				'title'       => __( 'Is Offense' ),
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
				'title'       => __( 'Path' ),
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
				'title'      => __( 'User ID' ),
				'className'  => 'uid',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => false,
			],
			'user'     => [
				'data'           => 'user',
				'title'          => __( 'User' ),
				'className'      => 'user',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true,
				],
			],
			'day'      => [
				'data'          => 'day',
				'title'         => __( 'Day' ),
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
				'title'         => __( 'Date' ),
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