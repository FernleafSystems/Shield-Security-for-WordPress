<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class LoggingDeprecatedCompatibilityIntegrationTest extends ShieldIntegrationTestCase {

	public function test_deprecated_methods_delegate_to_policy_values() :void {
		$con = $this->requireController();
		$activityPolicy = new ActivityLogRetentionPolicy();
		$requestPolicy = new RequestLogRetentionPolicy();

		$this->assertSame(
			\max( 1, (int)\ceil( $activityPolicy->defaultRetentionSeconds()/\DAY_IN_SECONDS ) ),
			$con->comps->activity_log->getAutoCleanDays()
		);
		$this->assertSame( [ 'warning', 'notice', 'info' ], $con->comps->activity_log->getLogLevelsDB() );
		$this->assertSame( $requestPolicy->retentionDays()[ 'standard' ], $con->comps->requests_log->getAutoCleanDays() );
	}

	public function test_deprecated_logging_methods_remain_stable_across_store_cycles() :void {
		$con = $this->requireController();
		$originalTrackingPermission = $con->opts->optGet( 'tracking_permission_set_at' );

		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ][ 'notice' ] = 52*\DAY_IN_SECONDS;
			return $policy;
		};
		$requestFilter = static function ( array $policy ) :array {
			$policy[ 'retention_days' ][ 'standard' ] = 19;
			return $policy;
		};

		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		add_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );

		try {
			for ( $i = 0; $i < 3; $i++ ) {
				$this->assertSame( 52, $con->comps->activity_log->getAutoCleanDays() );
				$this->assertSame( [ 'warning', 'notice', 'info' ], $con->comps->activity_log->getLogLevelsDB() );
				$this->assertSame( 19, $con->comps->requests_log->getAutoCleanDays() );

				$con->opts
					->optSet( 'tracking_permission_set_at', \time() + $i + 1 )
					->store();
			}

			$this->assertSame( 52, $con->comps->activity_log->getAutoCleanDays() );
			$this->assertSame( [ 'warning', 'notice', 'info' ], $con->comps->activity_log->getLogLevelsDB() );
			$this->assertSame( 19, $con->comps->requests_log->getAutoCleanDays() );
		}
		finally {
			$con->opts
				->optSet( 'tracking_permission_set_at', $originalTrackingPermission )
				->store();
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
			remove_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );
		}
	}
}
