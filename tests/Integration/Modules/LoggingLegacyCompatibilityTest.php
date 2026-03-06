<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class LoggingLegacyCompatibilityTest extends ShieldIntegrationTestCase {

	public function test_legacy_logging_option_reads_are_policy_derived() :void {
		$con = $this->requireController();

		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ][ 'notice' ] = 45*\DAY_IN_SECONDS;
			return $policy;
		};
		$requestFilter = static function ( array $policy ) :array {
			$policy[ 'retention_days' ][ 'standard' ] = 41;
			return $policy;
		};

		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		add_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );

		$opts = $con->opts;
		$this->assertTrue( $opts->optExists( 'log_level_db' ) );
		$this->assertTrue( $opts->optExists( 'audit_trail_auto_clean' ) );
		$this->assertTrue( $opts->optExists( 'auto_clean' ) );

		$this->assertSame( [ 'warning', 'notice', 'info' ], $opts->optGet( 'log_level_db' ) );
		$this->assertSame( 45, $opts->optGet( 'audit_trail_auto_clean' ) );
		$this->assertSame( 41, $opts->optGet( 'auto_clean' ) );
		$this->assertSame( [], $opts->optGet( 'type_exclusions' ) );
		$this->assertSame( [], $opts->optGet( 'custom_exclusions' ) );

		$opts->optSet( 'auto_clean', 999 );
		$opts->optSet( 'audit_trail_auto_clean', 999 );
		$this->assertSame( 41, $opts->optGet( 'auto_clean' ) );
		$this->assertSame( 45, $opts->optGet( 'audit_trail_auto_clean' ) );

		remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		remove_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );
	}

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

		$sanitizer = new PreSetOptSanitize( 'language_override', 'en' );
		$sanitizer->specificOptChecks();
		$this->assertSame( 'en', $sanitizer->run() );
	}

	public function test_legacy_logging_contract_remains_stable_across_store_cycles() :void {
		$con = $this->requireController();

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
			$opts = $con->opts;

			for ( $i = 0; $i < 3; $i++ ) {
				$this->assertSame( [ 'warning', 'notice', 'info' ], $opts->optGet( 'log_level_db' ) );
				$this->assertSame( 52, $opts->optGet( 'audit_trail_auto_clean' ) );
				$this->assertSame( 19, $opts->optGet( 'auto_clean' ) );
				$this->assertSame( [], $opts->optGet( 'type_exclusions' ) );
				$this->assertSame( [], $opts->optGet( 'custom_exclusions' ) );

				$this->assertSame( 52, $con->comps->activity_log->getAutoCleanDays() );
				$this->assertSame( [ 'warning', 'notice', 'info' ], $con->comps->activity_log->getLogLevelsDB() );
				$this->assertSame( 19, $con->comps->requests_log->getAutoCleanDays() );

				$opts->optSet( 'auto_clean', 900 + $i );
				$opts->optSet( 'audit_trail_auto_clean', 900 + $i );
				$opts->store();
			}

			$this->assertSame( [ 'warning', 'notice', 'info' ], $opts->optGet( 'log_level_db' ) );
			$this->assertSame( 52, $opts->optGet( 'audit_trail_auto_clean' ) );
			$this->assertSame( 19, $opts->optGet( 'auto_clean' ) );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
			remove_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );
		}
	}
}
