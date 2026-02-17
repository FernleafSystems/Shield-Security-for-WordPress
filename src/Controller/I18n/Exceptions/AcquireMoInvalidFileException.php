<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\Exceptions;

class AcquireMoInvalidFileException extends AcquireMoException {

	public function __construct() {
		parent::__construct( 'invalid_file' );
	}
}
