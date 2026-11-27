<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class FormidableForms extends Base {

	protected function run() {
		add_filter( 'frm_validate_entry', function ( $errors ) {
			if ( !\is_array( $errors ) || empty( $errors[ 'spam' ] ) ) {
				if ( $this->isBotBlockRequired() ) {
					if ( !\is_array( $errors ) ) {
						$errors = [];
					}
					// String taken from Formidable Forms' FrmEntryValidate.php. Formidable shows it to the visitor
					// as HTML, so the translation is stripped of markup.
					$errors[ 'spam' ] = wp_strip_all_tags( __( 'Your entry appears to be spam!', 'wp-simple-firewall' ) );
				}
			}
			return $errors;
		}, 1000 );
	}
}