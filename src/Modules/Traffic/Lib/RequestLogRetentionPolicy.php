<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RequestLogRetentionPolicy {

	use PluginControllerConsumer;

	/**
	 * Canonical filter for request log retention policy customisation.
	 */
	public const FILTER_REQUEST_POLICY = 'shield/request_logs/policy';

	/**
	 * Short retention tier for low-signal request logs.
	 */
	public const RETENTION_DAYS_TRANSIENT = 7;

	/**
	 * @deprecated 21.3 Use RETENTION_DAYS_TRANSIENT
	 */
	public const RETENTION_DAYS_SHORT = self::RETENTION_DAYS_TRANSIENT;

	/**
	 * Standard retention tier for normal request logs.
	 */
	public const RETENTION_DAYS_STANDARD = 30;

	/**
	 * @var array<string,mixed>|null
	 */
	private ?array $policy = null;

	/**
	 * @return array{retention_days:array{transient:int,standard:int}}
	 */
	public function policy() :array {
		if ( $this->policy === null ) {
			$this->policy = $this->buildPolicy();
		}
		return $this->policy;
	}

	/**
	 * @return array{transient:int,standard:int}
	 */
	public function retentionDays() :array {
		return $this->policy()[ 'retention_days' ];
	}

	/**
	 * @return array{transient:int,standard:int}
	 */
	public function retentionSeconds() :array {
		$days = $this->retentionDays();
		return [
			'transient' => $days[ 'transient' ]*\DAY_IN_SECONDS,
			'standard'  => $days[ 'standard' ]*\DAY_IN_SECONDS,
		];
	}

	public function shouldMarkAsTransient( array $requestMeta ) :bool {
		$flags = [
			'is_dependent' => $this->isDependentLog(),
			'has_params'   => !empty( $requestMeta[ 'has_params' ] ),
			'is_offense'   => !empty( $requestMeta[ 'offense' ] ),
		];

		return !$flags[ 'is_dependent' ]
			   && !$flags[ 'has_params' ]
			   && !$flags[ 'is_offense' ];
	}

	protected function isDependentLog() :bool {
		return self::con()->comps->requests_log->isDependentLog();
	}

	/**
	 * @return array{retention_days:array{transient:int,standard:int}}
	 */
	private function buildPolicy() :array {
		$defaults = [
			'retention_days' => [
				'transient' => self::RETENTION_DAYS_TRANSIENT,
				'standard'  => self::RETENTION_DAYS_STANDARD,
			],
		];

		$policy = (array)apply_filters( self::FILTER_REQUEST_POLICY, $defaults );
		$days = \array_merge(
			$defaults[ 'retention_days' ],
			\is_array( $policy[ 'retention_days' ] ?? null ) ? $policy[ 'retention_days' ] : []
		);

		$days[ 'transient' ] = \max( 1, (int)( $days[ 'transient' ] ?? self::RETENTION_DAYS_TRANSIENT ) );
		$days[ 'standard' ] = \max( $days[ 'transient' ], (int)( $days[ 'standard' ] ?? self::RETENTION_DAYS_STANDARD ) );

		return [
			'retention_days' => $days,
		];
	}
}
