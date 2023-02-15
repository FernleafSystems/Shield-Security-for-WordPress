<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForIpRules extends Base {

	protected function getOrderColumnSlug() :string {
		return 'last_access_at';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'ip',
			'ip_linked',
			'status',
			'type',
			'last_seen',
			'is_blocked',
			'unblocked_at',
			'last_access_at',
			'day',
			'date',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'ip'             => [
				'data'        => 'ip',
				'title'       => __( 'IP Address' ),
				'className'   => 'ip',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => false,
				],
			],
			'ip_linked'      => [
				'data'        => 'ip_linked',
				'title'       => __( 'IP Address or Range' ),
				'className'   => 'ip_linked',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
			'status'         => [
				'data'        => 'status',
				'title'       => __( 'Status' ),
				'className'   => 'status',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
			'type'           => [
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
			'last_seen'      => [
				'data'          => [
					'_'    => 'last_seen',
					'sort' => 'last_access_at',
				],
				'title'         => __( 'Last Seen' ),
				'className'     => 'date',
				'orderable'     => true,
				'orderSequence' => [ 'desc', 'asc' ],
				'searchable'    => false,
				'visible'       => true,
				'searchPanes'   => [
					'show' => false
				],
			],
			'day'            => [
				'data'          => 'day',
				'title'         => __( 'Last Access', 'wp-simple-firewall' ),
				'className'     => 'day',
				'orderable'     => false,
				'orderSequence' => [ 'desc' ],
				'searchable'    => false,
				'visible'       => false,
				'searchPanes'   => [
					'show' => true,
				],
			],
			'last_access_at' => [
				'data'          => 'last_access_at',
				'title'         => __( 'Last Access At' ),
				'className'     => 'date',
				'orderable'     => true,
				'orderSequence' => [ 'desc', 'asc' ],
				'searchable'    => false,
				'visible'       => false,
				'searchPanes'   => [
					'show' => false
				],
			],
			'is_blocked'     => [
				'data'        => 'is_blocked',
				'title'       => __( 'IP Block Status' ),
				'className'   => 'is_blocked',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => true
				],
			],
			'unblocked_at'   => [
				'data'        => 'unblocked_at',
				'title'       => __( 'Unblocked At' ),
				'className'   => 'unblocked_at',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => false
				],
			],
			'date'           => [
				'data'          => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'         => __( 'Date Added' ),
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