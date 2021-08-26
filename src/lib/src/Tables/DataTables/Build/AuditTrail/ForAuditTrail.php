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
				'className'   => 'level',
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
			'level'   => [
				'data'        => 'level',
				'title'       => __( 'Level' ),
				'className'   => 'level',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'ip'      => [
				'data'        => 'ip',
				'title'       => __( 'IP' ),
				'className'   => 'ip',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => true
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