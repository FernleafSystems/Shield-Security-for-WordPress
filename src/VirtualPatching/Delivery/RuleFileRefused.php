<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * A delivered rule file that failed a file check. The stored copy, if any, stays in place.
 */
final class RuleFileRefused extends \RuntimeException {

	public string $reason;

	public function __construct( string $reason ) {
		parent::__construct( $reason );
		$this->reason = $reason;
	}
}
