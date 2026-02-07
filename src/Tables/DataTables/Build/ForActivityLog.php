<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForActivityLog extends Base {

	protected function getOrderColumnSlug() :string {
		return 'date';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'severity',
			'identity',
			'ip',
			'level',
			'user',
			'event',
			'uid',
			'message',
			'date',
			'day',
			'rid',
			'meta',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'        => [
				'data'           => 'rid',
				'title'          => __( 'Request ID', 'wp-simple-firewall' ),
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
				'title'          => __( 'Event', 'wp-simple-firewall' ),
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
				'title'       => __( 'Event Slug', 'wp-simple-firewall' ),
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
				'title'       => '',
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
				'title'              => __( 'Level', 'wp-simple-firewall' ),
				'className'          => 'level',
				'orderable'          => false,
				'searchable'         => true,
				'search_builder'     => true,
				'visible'            => false,
				'searchPanes'        => [
					'show' => false
				],
				'searchBuilderTitle' => __( 'Severity', 'wp-simple-firewall' )
			],
			'ip'         => [
				'data'           => 'ip',
				'title'          => __( 'IP Address', 'wp-simple-firewall' ),
				'className'      => 'ip',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => true,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'identity'   => [
				'data'           => 'identity',
				'title'          => __( 'Identity', 'wp-simple-firewall' ),
				'className'      => 'identity',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => true,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'uid'        => [
				'data'           => 'uid',
				'title'          => __( 'User ID', 'wp-simple-firewall' ),
				'className'      => 'uid',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => true,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'user'       => [
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
			'message'    => [
				'data'        => 'message',
				'title'       => __( 'Log Message', 'wp-simple-firewall' ),
				'className'   => 'message',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'day'        => [
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
			'date'       => [
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
			'meta'       => [
				'data'        => 'meta',
				'title'       => __( 'Meta', 'wp-simple-firewall' ),
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