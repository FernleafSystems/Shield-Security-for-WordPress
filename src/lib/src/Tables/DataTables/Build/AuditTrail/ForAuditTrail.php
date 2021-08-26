<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;

class ForAuditTrail extends Base {

	protected function getOrderColumnSlug() :string {
		return 'date';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'severity',
			'req_details',
			'ip',
			'event',
			'level',
			'user',
			'message',
			'date',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'     => [
				'data'       => 'rid',
				'title'      => 'ID',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => false,
			],
			'event'   => [
				'data'        => 'event',
				'title'       => __( 'Event' ),
				'className'   => 'event',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => true
				],
			],
			'event_slug'   => [
				'data'        => 'event',
				'title'       => __( 'Event Slug' ),
				'className'   => 'event',
				'orderable'   => true,
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
			'level'   => [
				'data'        => 'level',
				'title'       => __( 'Severity' ),
				'className'   => 'level',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => true
				],
			],
			'ip'      => [
				'data'        => 'ip',
				'title'       => __( 'IP Address' ),
				'className'   => 'ip',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => false,
				'searchPanes' => [
					'show' => true,
				],
			],
			'req_details'      => [
				'data'        => 'req_details',
				'title'       => __( 'IP and Request Details' ),
				'className'   => 'req_details',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false,
				],
			],
			'uid'     => [
				'data'       => 'uid',
				'title'      => __( 'User ID' ),
				'className'  => 'uid',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => false,
			],
			'user'    => [
				'data'        => 'user',
				'title'       => __( 'User' ),
				'className'   => 'user',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => true
				],
			],
			'message' => [
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
			'date'    => [
				'data'        => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'       => __( 'Date' ),
				'className'   => 'date',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
		];
	}
}