<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions;

class ScanExistsException extends ScanException {

	public function __construct( string $scan ) {
		parent::__construct( $scan, sprintf( __( "Can't create a new scan when one already exists: %s", 'wp-simple-firewall' ), $scan ) );
	}
}
