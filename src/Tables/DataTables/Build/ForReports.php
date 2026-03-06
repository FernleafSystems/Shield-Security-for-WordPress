<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForReports extends Base {

	protected function getOrderColumnSlug() :string {
		return 'date';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'type',
			'title',
			'date',
			'actions',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'rid'     => [
				'data'           => 'rid',
				'title'          => __( 'ID', 'wp-simple-firewall' ),
				'className'      => 'rid',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'type'    => [
				'data'           => 'type',
				'title'          => __( 'Type', 'wp-simple-firewall' ),
				'className'      => 'type',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'title'   => [
				'data'           => 'title',
				'title'          => __( 'Title', 'wp-simple-firewall' ),
				'className'      => 'title',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'date'    => [
				'data'          => [
					'_'    => 'created_at_display',
					'sort' => 'created_at',
				],
				'title'         => __( 'Date Generated', 'wp-simple-firewall' ),
				'className'     => 'date',
				'orderable'     => true,
				'orderSequence' => [ 'desc', 'asc' ],
				'searchable'    => false,
				'visible'       => true,
				'searchPanes'   => [
					'show' => false,
				],
			],
			'actions' => [
				'data'           => 'actions',
				'title'          => __( 'Actions', 'wp-simple-firewall' ),
				'className'      => 'actions',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
		];
	}

	protected function getSearchPanesData() :array {
		return [];
	}
}
