<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForImportExportSites extends Base {

	protected function getOrderColumnSlug() :string {
		return 'url';
	}

	protected function getOrderMethod() :string {
		return 'asc';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'url',
			'status',
			'queue_status',
			'sync_status',
			'sync_state',
			'status_key',
			'queue_status_key',
			'actions',
			'updated_at',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'url'                     => [
				'data'        => 'url',
				'title'       => __( 'URL', 'wp-simple-firewall' ),
				'className'   => 'url',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'status'                  => [
				'data'        => [
					'_'    => 'status',
					'sort' => 'status',
				],
				'title'       => __( 'Registration', 'wp-simple-firewall' ),
				'className'   => 'status',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'queue_status'            => [
				'data'        => [
					'_'    => 'queue_status',
					'sort' => 'queue_status',
				],
				'title'       => __( 'Queue', 'wp-simple-firewall' ),
				'className'   => 'queue_status',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'sync_status'             => [
				'data'        => [
					'_'    => 'sync_status',
					'sort' => 'updated_at',
				],
				'title'       => __( 'Current Sync', 'wp-simple-firewall' ),
				'className'   => 'sync_status',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'sync_state'              => [
				'data'        => 'sync_state',
				'title'       => __( 'Current Sync', 'wp-simple-firewall' ),
				'className'   => 'sync_state',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [ 'show' => true ],
			],
			'status_key'              => [
				'data'        => 'status_key',
				'title'       => __( 'Registration', 'wp-simple-firewall' ),
				'className'   => 'status_key',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [ 'show' => true ],
			],
			'queue_status_key'        => [
				'data'        => 'queue_status_key',
				'title'       => __( 'Queue', 'wp-simple-firewall' ),
				'className'   => 'queue_status_key',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [ 'show' => true ],
			],
			'actions'                 => [
				'data'        => 'actions',
				'title'       => '',
				'className'   => 'actions text-end',
				'width'       => '5rem',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [ 'show' => false ],
			],
			'updated_at'              => [
				'data'        => 'updated_at',
				'title'       => __( 'Updated At', 'wp-simple-firewall' ),
				'className'   => 'updated_at',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [ 'show' => false ],
			],
		];
	}
}
