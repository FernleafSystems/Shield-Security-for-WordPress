<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * A rule file that passed every file check. Its ordering, (epoch of its key, sequence), is always read from the
 * envelope, never kept elsewhere.
 */
final class VerifiedRuleFile {

	public string $bytes;

	public string $keyId;

	public int $epoch;

	public int $sequence;

	public \stdClass $payload;

	public function __construct( string $bytes, string $keyId, int $epoch, int $sequence, \stdClass $payload ) {
		$this->bytes = $bytes;
		$this->keyId = $keyId;
		$this->epoch = $epoch;
		$this->sequence = $sequence;
		$this->payload = $payload;
	}

	public function sha256() :string {
		return \hash( 'sha256', $this->bytes );
	}

	/**
	 * Negative, zero or positive as this file orders below, with or above the other.
	 */
	public function compareOrdering( VerifiedRuleFile $other ) :int {
		return $this->epoch === $other->epoch ? $this->sequence <=> $other->sequence : $this->epoch <=> $other->epoch;
	}
}
