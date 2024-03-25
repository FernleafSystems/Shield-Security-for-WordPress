<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;

class BaseForScan extends Base {

	protected function getOrderColumnSlug() :string {
		return 'detected';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'status_file_type',
			'status_file_size',
			'file_as_href',
			'status',
			'file_type',
			'detected',
			'actions',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'              => [
				'data'        => 'rid',
				'title'       => 'ID',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => false
				],
			],
			'file'             => [
				'data'        => 'file',
				'title'       => __( 'File' ),
				'className'   => 'file',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'file_as_href'     => [
				'data'        => [
					'_'    => 'file_as_href',
					'sort' => 'file',
				],
				'title'       => __( 'File' ),
				'className'   => 'file_as_href',
				'orderable'   => false,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'file_type'        => [
				'data'        => 'file_type',
				'title'       => __( 'Type' ),
				'className'   => 'file_type',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => true
				],
			],
			'status_file_type' => [
				'data'        => 'status_file_type',
				'title'       => __( 'Type' ),
				'className'   => 'status_file_type',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'status_file_size' => [
				'data'        => 'status_file_size',
				'title'       => __( 'Size' ),
				'className'   => 'status_file_size',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'status'           => [
				'data'        => 'status',
				'title'       => __( 'Status' ),
				'className'   => 'status',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => true
				],
			],
			'detected'         => [
				'data'        => [
					'_'    => 'detected_since',
					'sort' => 'created_at',
				],
				'title'       => __( 'Detected' ),
				'className'   => 'detected',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'actions'          => [
				'data'        => 'actions',
				'title'       => __( 'Actions' ),
				'className'   => 'actions',
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