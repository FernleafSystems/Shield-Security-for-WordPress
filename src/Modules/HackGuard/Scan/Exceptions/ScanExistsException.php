<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions;

class ScanExistsException extends ScanException {

	private int $existingScanID;

	public function __construct( string $scan, int $existingScanID = 0 ) {
		$this->existingScanID = $existingScanID;
		parent::__construct( $scan, sprintf( __( "Can't create a new scan when one already exists: %s", 'wp-simple-firewall' ), $scan ) );
	}

	public function getExistingScanID() :int {
		return $this->existingScanID;
	}
}
