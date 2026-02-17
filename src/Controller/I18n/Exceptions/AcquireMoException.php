<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\Exceptions;

class AcquireMoException extends \RuntimeException {

	private string $reason;

	public function __construct( string $reason ) {
		parent::__construct( $reason );
		$this->reason = $reason;
	}

	public function reason() :string {
		return $this->reason;
	}
}
