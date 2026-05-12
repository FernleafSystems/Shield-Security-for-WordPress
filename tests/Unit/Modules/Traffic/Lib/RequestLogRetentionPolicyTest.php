<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Traffic\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestLogRetentionPolicyTest extends BaseUnitTest {

	private function setApplyFilters( ?callable $callback = null ) :void {
		Functions\when( 'apply_filters' )->alias( function ( string $tag, $value = null, ...$args ) use ( $callback ) {
			if ( \is_callable( $callback ) ) {
				$result = $callback( $tag, $value, $args );
				if ( $result !== null ) {
					return $result;
				}
			}
			return $value;
		} );
	}

	private function makePolicy( bool $dependent = false ) :RequestLogRetentionPolicy {
		return new class( $dependent ) extends RequestLogRetentionPolicy {

			private bool $dependent;

			public function __construct( bool $dependent ) {
				$this->dependent = $dependent;
			}

			protected function isDependentLog() :bool {
				return $this->dependent;
			}
		};
	}

	public function test_retention_days_default_to_constants() :void {
		$this->setApplyFilters();
		$policy = $this->makePolicy();

		$this->assertSame( [
			'transient' => RequestLogRetentionPolicy::RETENTION_DAYS_TRANSIENT,
			'standard'  => RequestLogRetentionPolicy::RETENTION_DAYS_STANDARD,
		], $policy->retentionDays() );
	}

	public function test_retention_days_filter_normalises_invalid_ranges() :void {
		$this->setApplyFilters( function ( string $tag, $value ) {
			if ( $tag === RequestLogRetentionPolicy::FILTER_REQUEST_POLICY ) {
				$value[ 'retention_days' ] = [
					'transient' => 0,
					'standard'  => -5,
				];
				return $value;
			}
			return null;
		} );

		$days = $this->makePolicy()->retentionDays();
		$this->assertSame( 1, $days[ 'transient' ] );
		$this->assertSame( 1, $days[ 'standard' ] );
	}

	public function test_retention_days_filter_ignores_non_array_policy_payload() :void {
		$this->setApplyFilters( function ( string $tag, $value ) {
			if ( $tag === RequestLogRetentionPolicy::FILTER_REQUEST_POLICY ) {
				return 'not-an-array';
			}
			return null;
		} );

		$this->assertSame( [
			'transient' => RequestLogRetentionPolicy::RETENTION_DAYS_TRANSIENT,
			'standard'  => RequestLogRetentionPolicy::RETENTION_DAYS_STANDARD,
		], $this->makePolicy()->retentionDays() );
	}

	public function test_retention_days_filter_normalises_missing_and_malformed_values() :void {
		$this->setApplyFilters( function ( string $tag, $value ) {
			if ( $tag === RequestLogRetentionPolicy::FILTER_REQUEST_POLICY ) {
				$value[ 'retention_days' ] = [
					'transient' => '12',
					'standard'  => 'abc',
				];
				return $value;
			}
			return null;
		} );

		$days = $this->makePolicy()->retentionDays();
		$this->assertSame( 12, $days[ 'transient' ] );
		$this->assertSame( 12, $days[ 'standard' ] );
	}

	public function test_retention_days_filter_handles_missing_retention_days_key() :void {
		$this->setApplyFilters( function ( string $tag, $value ) {
			if ( $tag === RequestLogRetentionPolicy::FILTER_REQUEST_POLICY ) {
				unset( $value[ 'retention_days' ] );
				return $value;
			}
			return null;
		} );

		$this->assertSame( [
			'transient' => RequestLogRetentionPolicy::RETENTION_DAYS_TRANSIENT,
			'standard'  => RequestLogRetentionPolicy::RETENTION_DAYS_STANDARD,
		], $this->makePolicy()->retentionDays() );
	}

	public function test_transient_decision_matrix() :void {
		$this->setApplyFilters();

		$this->assertTrue( $this->makePolicy()->shouldMarkAsTransient( [
			'has_params' => 0,
			'offense'    => 0,
		] ) );

		$this->assertFalse( $this->makePolicy()->shouldMarkAsTransient( [
			'has_params' => 1,
			'offense'    => 0,
		] ) );

		$this->assertFalse( $this->makePolicy()->shouldMarkAsTransient( [
			'has_params' => 0,
			'offense'    => 1,
		] ) );

		$this->assertFalse( $this->makePolicy( true )->shouldMarkAsTransient( [
			'has_params' => 0,
			'offense'    => 0,
		] ) );
	}
}
