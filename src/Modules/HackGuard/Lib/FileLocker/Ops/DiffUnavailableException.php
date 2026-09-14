<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

final class DiffUnavailableException extends \RuntimeException {

	public function __construct( \Throwable $previous ) {
		parent::__construct(
			__( 'The file comparison could not be generated.', 'wp-simple-firewall' ),
			0,
			$previous
		);
	}
}
