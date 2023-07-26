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
					// string taken from Formidable forms FrmEntryValidate.php
					$errors[ 'spam' ] = __( 'Your entry appears to be spam!', 'formidable' );
				}
			}
			return $errors;
		}, 1000 );
	}
}