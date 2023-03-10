<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;

class BaseForScan extends Base {

	public const MOD = ModCon::SLUG;

	protected function getOrderColumnSlug() :string {
		return 'detected';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'file_as_href',
			'status',
			'file_type',
			'detected',
			'actions',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'               => [
				'data'        => 'rid',
				'title'       => 'ID',
				'orderable'   => true,
				'searchable'  => false,
				'visible'     => false,
				'searchPanes' => [
					'show' => false
				],
			],
			'file'              => [
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
			'file_as_href'      => [
				'data'        => [
					'_'    => 'file_as_href',
					'sort' => 'file',
				],
				'title'       => __( 'File' ),
				'className'   => 'file_as_href',
				'orderable'   => true,
				'searchable'  => true,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'file_type'         => [
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
			'status'            => [
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
			'detected'          => [
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
			'actions'           => [
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