<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions;

class ScanCreateException extends ScanException {

	public function __construct( string $scan, string $message = '' ) {
		parent::__construct(
			$scan,
			empty( $message ) ? sprintf( 'Failed to create/insert a new scan "%s".', $scan ) : $message
		);
	}
}
