<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

class OptionAuditValueFormatter {

	/**
	 * @param mixed $value
	 */
	public function format( array $optDef, $value ) :string {
		if ( !empty( $optDef[ 'sensitive' ] ) ) {
			return __( 'redacted', 'wp-simple-firewall' );
		}

		if ( $optDef[ 'type' ] === 'checkbox' ) {
			$value = $value === 'Y' ? 'on' : 'off';
		}
		elseif ( !\is_scalar( $value ) ) {
			switch ( $optDef[ 'type' ] ) {
				case 'array':
				case 'multiple_select':
					$value = \implode( ', ', $value );
					break;
				default:
					$value = sprintf( __( '%s (JSON Encoded)', 'wp-simple-firewall' ), \wp_json_encode( $value ) );
					break;
			}
		}

		return (string)$value;
	}
}
