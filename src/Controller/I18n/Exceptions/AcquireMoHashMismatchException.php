<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\Exceptions;

class AcquireMoHashMismatchException extends AcquireMoException {

	public function __construct() {
		parent::__construct( 'hash_mismatch' );
	}
}
