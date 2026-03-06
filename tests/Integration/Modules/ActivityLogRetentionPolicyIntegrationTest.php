<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ActivityLogRetentionPolicyIntegrationTest extends ShieldIntegrationTestCase {

	private function firstEventForLevel( string $level, array $exclude = [] ) :string {
		foreach ( $this->requireController()->comps->events->getEvents() as $event => $def ) {
			if ( ( $def[ 'level' ] ?? 'notice' ) === $level && !\in_array( $event, $exclude, true ) ) {
				return $event;
			}
		}
		$this->markTestSkipped( sprintf( "No event found for level '%s'.", $level ) );
		return '';
	}

	public function test_policy_normalises_malformed_payloads_to_safe_values() :void {
		$infoEvent = $this->firstEventForLevel( 'info' );
		$activityFilter = static function ( array $policy ) use ( $infoEvent ) :array {
			$policy[ 'retention_seconds_by_level' ] = [
				'info'    => 'invalid',
				'notice'  => -100,
				'warning' => 0,
				'custom'  => 999,
			];
			$policy[ 'high_value_events' ] = [
				123,
				'',
				[],
				'not_a_real_event',
				$infoEvent,
			];
			$policy[ 'high_value_retention_seconds' ] = -50;
			$policy[ 'retention_seconds_by_event' ] = [
				''           => 999,
				123          => 999,
				$infoEvent   => -20,
				'custom_evt' => 2*\HOUR_IN_SECONDS,
			];
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );

		try {
			$policy = new ActivityLogRetentionPolicy();
			$retentionByLevel = $policy->retentionSecondsByLevel();
			$retentionByEvent = $policy->retentionSecondsByEvent();

			$this->assertSame( \HOUR_IN_SECONDS, $retentionByLevel[ 'info' ] );
			$this->assertSame( \HOUR_IN_SECONDS, $retentionByLevel[ 'notice' ] );
			$this->assertSame( \HOUR_IN_SECONDS, $retentionByLevel[ 'warning' ] );
			$this->assertSame( \HOUR_IN_SECONDS, $retentionByLevel[ 'custom' ] );

			$this->assertSame( \DAY_IN_SECONDS, $policy->highValueRetentionSeconds() );
			$this->assertSame( [ $infoEvent ], $policy->highValueEventSlugs() );
			$this->assertSame( [ 'warning', 'notice', 'info' ], $policy->canonicalLevels() );

			$this->assertSame( \HOUR_IN_SECONDS, $retentionByEvent[ $infoEvent ] );
			$this->assertSame( 2*\HOUR_IN_SECONDS, $retentionByEvent[ 'custom_evt' ] );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}

	public function test_policy_precedence_event_override_then_high_value_then_level_default() :void {
		$defaultHighValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();
		$highValueEvent = \current( $defaultHighValueEvents );
		if ( !\is_string( $highValueEvent ) || empty( $highValueEvent ) ) {
			$this->markTestSkipped( 'No high-value event slugs were available.' );
		}

		$warningEvent = $this->firstEventForLevel( 'warning', $defaultHighValueEvents );
		$infoEvent = $this->firstEventForLevel( 'info', $defaultHighValueEvents );

		$activityFilter = static function ( array $policy ) use ( $highValueEvent, $warningEvent ) :array {
			$policy[ 'retention_seconds_by_level' ] = [
				'info'    => 2*\HOUR_IN_SECONDS,
				'notice'  => 3*\HOUR_IN_SECONDS,
				'warning' => 4*\HOUR_IN_SECONDS,
			];
			$policy[ 'high_value_events' ] = [
				$highValueEvent,
			];
			$policy[ 'high_value_retention_seconds' ] = 5*\HOUR_IN_SECONDS;
			$policy[ 'retention_seconds_by_event' ] = [
				$highValueEvent => 6*\HOUR_IN_SECONDS,
				$warningEvent   => 7*\HOUR_IN_SECONDS,
			];
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );

		try {
			$retentionByEvent = ( new ActivityLogRetentionPolicy() )->retentionSecondsByEvent();
			$this->assertSame( 7*\HOUR_IN_SECONDS, $retentionByEvent[ $warningEvent ] );
			$this->assertSame( 6*\HOUR_IN_SECONDS, $retentionByEvent[ $highValueEvent ] );
			$this->assertSame( 2*\HOUR_IN_SECONDS, $retentionByEvent[ $infoEvent ] );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}
}
