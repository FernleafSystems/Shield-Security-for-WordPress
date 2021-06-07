<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanBase extends Base {

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_path( $item ) {
		$output = sprintf( '<code><span class="font-weight-bolder text-dark" style="font-size: larger">%s</span></code><code>[%s]</code>',
			$item[ 'path' ],
			sprintf( '%s: %s', __( 'Path', 'wp-simple-firewall' ), trailingslashit( dirname( $item[ 'path_relabs' ] ) ) )
		);
		if ( !empty( $item[ 'path_details' ] ) ) {
			$output .= '<p class="mb-0">'.implode( '; ', $item[ 'path_details' ] ).'</p>';
		}
		return $output;
	}

//.implode( '; ', $aItem[ 'asset_description' ] )
	protected function extra_tablenav( $which ) {
		echo '';
	}

	/**
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array_merge( parent::get_table_classes(), [ 'scan-table' ] );
	}

	/**
	 * @param string $sHref
	 * @return string
	 */
	protected function getActionButton_DownloadFile( $sHref ) {
		return $this->buildActionButton_Custom(
			__( 'Download', 'wp-simple-firewall' ),
			[ 'href-download', 'text-info' ],
			[ 'href-download' => $sHref ]
		);
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'path'       => __( 'File', 'wp-simple-firewall' ),
			'status'     => __( 'Status', 'wp-simple-firewall' ),
			'created_at' => __( 'Discovered', 'wp-simple-firewall' ),
		];
	}
}