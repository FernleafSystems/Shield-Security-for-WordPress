<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

class ForScansHistory extends Base {

	protected function getOrderColumnSlug() :string {
		return 'exec_order';
	}

	protected function getColumnsToDisplay() :array {
		return [
			'id',
			'slug',
		];
	}

	protected function getColumnDefs() :array {
		return [
			'active'      => [
				'data'           => 'active',
				'title'          => __( 'Active', 'wp-simple-firewall' ),
				'className'      => 'active',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'id'          => [
				'data'           => 'id',
				'title'          => __( 'ID', 'wp-simple-firewall' ),
				'className'      => 'id',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'slug'        => [
				'data'           => 'slug',
				'title'          => __( 'Slug', 'wp-simple-firewall' ),
				'className'      => 'slug',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'exec_order'  => [
				'data'           => 'exec_order',
				'title'          => __( 'Exec Order', 'wp-simple-firewall' ),
				'className'      => 'exec_order',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'details'     => [
				'data'           => 'details',
				'title'          => __( 'Details', 'wp-simple-firewall' ),
				'className'      => 'details',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => true,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'name'        => [
				'data'           => 'name',
				'title'          => __( 'Name', 'wp-simple-firewall' ),
				'className'      => 'name',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'description' => [
				'data'           => 'description',
				'title'          => __( 'Description', 'wp-simple-firewall' ),
				'className'      => 'description',
				'orderable'      => false,
				'searchable'     => true,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => false,
				],
			],
			'version'     => [
				'data'           => 'version',
				'title'          => __( 'Version', 'wp-simple-firewall' ),
				'className'      => 'version',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true
				],
			],
			'is_viable'   => [
				'data'           => 'is_viable',
				'title'          => __( 'Is Viable', 'wp-simple-firewall' ),
				'className'      => 'is_viable',
				'orderable'      => false,
				'searchable'     => false,
				'search_builder' => false,
				'visible'        => false,
				'searchPanes'    => [
					'show' => true
				],
			],
			'date'        => [
				'data'          => [
					'_'    => 'created_since',
					'sort' => 'created_at',
				],
				'title'         => __( 'Modified', 'wp-simple-firewall' ),
				'className'     => 'date',
				'orderable'     => false,
				'orderSequence' => [ 'desc', 'asc' ],
				'searchable'    => false,
				'visible'       => true,
				'searchPanes'   => [
					'show' => false
				],
			],
			'actions'     => [
				'data'        => 'actions',
				'title'       => __( 'Actions', 'wp-simple-firewall' ),
				'className'   => 'actions',
				'orderable'   => false,
				'searchable'  => false,
				'visible'     => true,
				'searchPanes' => [
					'show' => false
				],
			],
			'drag'        => [
				'data'           => 'drag',
				'title'          => '',
				'className'      => 'drag',
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
}