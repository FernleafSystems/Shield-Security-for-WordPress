<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\NotificationMetadata;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NotificationMetadataTest extends BaseUnitTest {

	public function test_attempt_count_is_normalized_to_the_supported_range() :void {
		$metadata = new NotificationMetadata();

		$this->assertSame( 0, $metadata->attemptsStarted( [] ) );
		$this->assertSame( 0, $metadata->attemptsStarted( [ NotificationMetadata::META_KEY => -4 ] ) );
		$this->assertSame( 2, $metadata->attemptsStarted( [ NotificationMetadata::META_KEY => '2' ] ) );
		$this->assertSame(
			NotificationMetadata::MAX_ATTEMPTS,
			$metadata->attemptsStarted( [ NotificationMetadata::META_KEY => 99 ] )
		);
	}

	public function test_cycle_updates_preserve_unrelated_metadata() :void {
		$metadata = new NotificationMetadata();
		$meta = [
			'invitation'            => [ 'cycle_id' => 'preserved' ],
			'export_served_at'      => 100,
			'handshake_attempt_at'  => 200,
		];

		$fresh = $metadata->startFreshCycle( $meta );
		$second = $metadata->increment( $fresh );
		$reset = $metadata->reset( $second );

		$this->assertSame( 1, $metadata->attemptsStarted( $fresh ) );
		$this->assertSame( 2, $metadata->attemptsStarted( $second ) );
		$this->assertSame( 0, $metadata->attemptsStarted( $reset ) );
		$this->assertSame( $meta, \array_diff_key( $reset, [ NotificationMetadata::META_KEY => true ] ) );
	}
}
