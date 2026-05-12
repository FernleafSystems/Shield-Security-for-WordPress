<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

trait ActionRequestNonceFixture {

	use CurrentRequestFixture;

	/**
	 * Seed canonical action transport fields into request bags used by nonce verification.
	 *
	 * @param class-string $actionClass
	 * @return array<string,array>
	 */
	private function seedActionNonceContext( string $actionClass ) :array {
		$snapshot = $this->snapshotCurrentRequestBags();
		$this->mergeCurrentRequestTransport( $this->canonicalShieldTransportFor( $actionClass ) );

		return $snapshot;
	}

	/**
	 * @param array<string,array> $snapshot
	 */
	private function restoreActionNonceContext( array $snapshot ) :void {
		$this->restoreCurrentRequestBags( $snapshot );
	}
}
