<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\ImportExport;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\InvitationMetadata;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvitationMetadataTest extends BaseUnitTest {

	public function test_normalizes_legacy_and_untrusted_values() :void {
		$metadata = new InvitationMetadata();

		$this->assertSame( [
			'cycle_id'               => '',
			'attempts_started'        => 0,
			'last_attempt_started_at' => null,
			'last_result'             => null,
			'last_http_status'        => null,
		], $metadata->normalize( [] ) );
		$this->assertSame( [
			'cycle_id'               => 'cycle',
			'attempts_started'        => InvitationMetadata::MAX_ATTEMPTS,
			'last_attempt_started_at' => 123,
			'last_result'             => null,
			'last_http_status'        => null,
		], $metadata->normalize( [
			InvitationMetadata::META_KEY => [
				'cycle_id'               => 'cycle',
				'attempts_started'        => 99,
				'last_attempt_started_at' => 123,
				'last_result'             => 'raw-error-text',
				'last_http_status'        => -1,
			],
		] ) );
	}

	public function test_new_cycle_and_updates_preserve_unrelated_metadata() :void {
		Functions\expect( 'wp_generate_uuid4' )->once()->andReturn( 'unique-cycle' );
		$metadata = new InvitationMetadata();
		$meta = $metadata->replace( [ 'export_served_at' => 100 ], $metadata->newCycle() );
		$meta = $metadata->startAttempt( $meta, 200 );
		$meta = $metadata->withResult( $meta, InvitationMetadata::RESULT_HTTP_FAILURE, 503 );

		$this->assertSame( 100, $meta[ 'export_served_at' ] );
		$this->assertSame( [
			'cycle_id'               => 'unique-cycle',
			'attempts_started'        => 1,
			'last_attempt_started_at' => 200,
			'last_result'             => InvitationMetadata::RESULT_HTTP_FAILURE,
			'last_http_status'        => 503,
		], $metadata->normalize( $meta ) );
	}
}
