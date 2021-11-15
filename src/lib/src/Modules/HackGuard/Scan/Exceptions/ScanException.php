<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions;

class ScanException extends \Exception {

	protected $scan;

	public function __construct( string $scan, string $message = '' ) {
		$this->scan = $scan;
		parent::__construct( empty( $message ) ? sprintf( 'Scan Exception: %s', $scan ) : $message );
	}
}
