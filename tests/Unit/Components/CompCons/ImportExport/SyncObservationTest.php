<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics\SyncObservation;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SyncObservationTest extends BaseUnitTest {

	public function test_builds_canonical_bounded_observation() :void {
		$observation = SyncObservation::create(
			123,
			SyncObservation::PHASE_CLIENT_IMPORT,
			SyncObservation::RESULT_EMPTY_RESPONSE,
			SyncObservation::VERIFICATION_NOT_APPLICABLE,
			[
				'http_status'       => 403,
				'target_fingerprint' => \str_repeat( 'a', 64 ),
				'ignored_secret'     => 'must-not-survive',
			]
		);

		$this->assertSame( [
			'observed_at'       => 123,
			'phase'             => SyncObservation::PHASE_CLIENT_IMPORT,
			'result'            => SyncObservation::RESULT_EMPTY_RESPONSE,
			'verification'      => SyncObservation::VERIFICATION_NOT_APPLICABLE,
			'http_status'       => 403,
			'target_fingerprint' => \str_repeat( 'a', 64 ),
		], $observation );
		$this->assertLessThanOrEqual( SyncObservation::MAX_ENCODED_BYTES, \strlen( (string)\json_encode( $observation ) ) );
	}

	public function test_rejects_invalid_contract_values() :void {
		$this->assertNull( SyncObservation::create( 123, SyncObservation::PHASE_EXPORT, 'invented', SyncObservation::VERIFICATION_ESTABLISHED ) );
		$this->assertNull( SyncObservation::normalize( [
			'observed_at'       => 123,
			'phase'             => SyncObservation::PHASE_EXPORT,
			'result'            => SyncObservation::RESULT_EXPORT_SERVED,
			'verification'      => SyncObservation::VERIFICATION_ESTABLISHED,
			'target_fingerprint' => \str_repeat( 'a', 64 ),
		] ) );
	}

	public function test_fingerprint_is_opaque_and_deterministic() :void {
		$fingerprint = SyncObservation::targetFingerprint( 'https://master.example.com/path' );
		$this->assertSame( 64, \strlen( $fingerprint ) );
		$this->assertSame( $fingerprint, SyncObservation::targetFingerprint( 'https://master.example.com/path' ) );
		$this->assertStringNotContainsString( 'master.example.com', $fingerprint );
	}
}
