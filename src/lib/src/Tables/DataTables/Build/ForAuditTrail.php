<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForAuditTrail extends Base {

	protected function getOrderColumnSlug() :string {
		return 'date';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'severity',
			'ip_linked',
			'ip',
			'event',
			'level',
			'user',
			'message',
			'date',
			'rid',
			'meta',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'        => [
				'data'           => 'rid',
				'title'          => __( 'Request ID' ),
				'className'      => 'rid',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => true,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'event'      => [
				'data'           => 'event',
				'title'          => __( 'Event' ),
				'className'      => 'event',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => true,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true
				],
			],
			'event_slug' => [
				'data'        => 'event',
				'title'       => __( 'Event Slug' ),
				'className'   => 'event',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => false
				],
			],
			'severity'   => [
				'data'        => 'severity',
				'title'       => __( 'Severity' ),
				'className'   => 'severity',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'level'      => [
				'data'               => 'level',
				'title'              => __( 'Level' ),
				'className'          => 'level',
				'orderable'          => false,
				'searchable'         => true,
				'search_builder'     => true,
				'visible'            => false,
				'searchPanes'        => [
					'show' => false
				],
				'searchBuilderTitle' => __( 'Severity' )
			],
			'ip'         => [
				'data'           => 'ip',
				'title'          => __( 'IP Address' ),
				'className'      => 'ip',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => true,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true,
				],
			],
			'ip_linked'  => [
				'data'        => 'ip_linked',
				'title'       => __( 'IP' ),
				'className'   => 'ip_linked',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
			'uid'        => [
				'data'           => 'uid',
				'title'          => __( 'User ID' ),
				'className'      => 'uid',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => true,
				'visible'        => false,
			],
			'user'       => [
				'data'           => 'user',
				'title'          => __( 'User' ),
				'className'      => 'user',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false
				],
			],
			'message'    => [
				'data'        => 'message',
				'title'       => __( 'Message' ),
				'className'   => 'message',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'date'       => [
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
			'meta'       => [
				'data'        => 'meta',
				'title'       => __( 'Meta' ),
				'className'   => 'meta',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
		];
	}
}