<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions;

class ScanExistsException extends ScanException {

	public function __construct( string $scan ) {
		parent::__construct( $scan, sprintf( "Can't create new scan where one already exists: %s", $scan ) );
	}
}
