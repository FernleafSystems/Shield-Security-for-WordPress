<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class IpBase extends Base {

	public function column_ip( array $item ) :string {
		return $item[ 'ip' ].$this->buildActions( [ $this->getActionButton_Delete( $item[ 'id' ] ) ] );
	}

	public function column_label( array $item ) :string {
		return esc_html( empty( $item[ 'label' ] ) ? __( 'No Label', 'wp-simple-firewall' ) : $item[ 'label' ] );
	}

	public function get_columns() {
		return [
			'ip'             => __( 'IP Address' ),
			'label'          => __( 'Label', 'wp-simple-firewall' ),
			'transgressions' => __( 'Offenses', 'wp-simple-firewall' ),
			'list'           => __( 'List', 'wp-simple-firewall' ),
			'last_access_at' => __( 'Last Access', 'wp-simple-firewall' ),
			'created_at'     => __( 'Date' ),
		];
	}
}