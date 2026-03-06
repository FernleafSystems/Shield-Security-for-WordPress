<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActivityLogRetentionPolicy {

	use PluginControllerConsumer;

	/**
	 * Canonical filter for all activity log retention policy customisation.
	 */
	public const FILTER_ACTIVITY_POLICY = 'shield/activity_logs/policy';

	/**
	 * Lowest-signal events.
	 */
	public const RETENTION_INFO_HOURS = 24;

	/**
	 * Default event retention baseline.
	 */
	public const RETENTION_NOTICE_DAYS = 30;

	/**
	 * Warning and security-oriented events.
	 */
	public const RETENTION_WARNING_DAYS = 180;

	/**
	 * High-value lifecycle and security changes.
	 */
	public const RETENTION_HIGH_VALUE_DAYS = 730;

	/**
	 * @var array<string,mixed>|null
	 */
	private ?array $policy = null;

	/**
	 * @return array{
	 *   retention_seconds_by_level:array<string,int>,
	 *   high_value_events:string[],
	 *   high_value_retention_seconds:int,
	 *   retention_seconds_by_event:array<string,int>
	 * }
	 */
	public function policy() :array {
		if ( $this->policy === null ) {
			$this->policy = $this->buildPolicy();
		}
		return $this->policy;
	}

	public function defaultRetentionSeconds() :int {
		return $this->retentionSecondsByLevel()[ 'notice' ] ?? self::RETENTION_NOTICE_DAYS*\DAY_IN_SECONDS;
	}

	public function retentionSecondsByLevel() :array {
		return $this->policy()[ 'retention_seconds_by_level' ];
	}

	/**
	 * @return string[]
	 */
	public function highValueEventSlugs() :array {
		return $this->policy()[ 'high_value_events' ];
	}

	public function highValueRetentionSeconds() :int {
		return $this->policy()[ 'high_value_retention_seconds' ];
	}

	/**
	 * @return string[]
	 */
	public function canonicalLevels() :array {
		$available = \array_keys( $this->retentionSecondsByLevel() );
		$levels = \array_values( \array_filter(
			[
				'warning',
				'notice',
				'info',
			],
			fn( string $level ) => \in_array( $level, $available, true )
		) );
		return empty( $levels ) ? [ 'warning', 'notice', 'info' ] : $levels;
	}

	/**
	 * @return array<string,int>
	 */
	public function retentionSecondsByEvent() :array {
		$retentionByLevel = $this->retentionSecondsByLevel();
		$retentionByEvent = [];

		foreach ( self::con()->comps->events->getEvents() as $event => $def ) {
			$level = (string)( $def[ 'level' ] ?? 'notice' );
			$retentionByEvent[ $event ] = $retentionByLevel[ $level ] ?? $retentionByLevel[ 'notice' ];
		}

		foreach ( $this->highValueEventSlugs() as $event ) {
			$retentionByEvent[ $event ] = $this->highValueRetentionSeconds();
		}

		$retentionByEvent = \array_merge( $retentionByEvent, $this->policy()[ 'retention_seconds_by_event' ] );

		return \array_map(
			fn( $seconds ) => \max( \HOUR_IN_SECONDS, (int)$seconds ),
			$retentionByEvent,
		);
	}

	private function buildPolicy() :array {
		$defaults = [
			'retention_seconds_by_level' => [
				'info'    => self::RETENTION_INFO_HOURS*\HOUR_IN_SECONDS,
				'notice'  => self::RETENTION_NOTICE_DAYS*\DAY_IN_SECONDS,
				'warning' => self::RETENTION_WARNING_DAYS*\DAY_IN_SECONDS,
			],
			'high_value_events'           => ( new HighValueEvents() )->forDashboardTicker(),
			'high_value_retention_seconds' => self::RETENTION_HIGH_VALUE_DAYS*\DAY_IN_SECONDS,
			'retention_seconds_by_event'  => [],
		];

		$policy = (array)apply_filters( self::FILTER_ACTIVITY_POLICY, $defaults );
		$retentionByLevel = \array_merge(
			$defaults[ 'retention_seconds_by_level' ],
			\is_array( $policy[ 'retention_seconds_by_level' ] ?? null ) ? $policy[ 'retention_seconds_by_level' ] : []
		);
		$retentionByEvent = \is_array( $policy[ 'retention_seconds_by_event' ] ?? null ) ? $policy[ 'retention_seconds_by_event' ] : [];
		$highValueEvents = \is_array( $policy[ 'high_value_events' ] ?? null ) ? $policy[ 'high_value_events' ] : [];

		return [
			'retention_seconds_by_level'  => \array_map(
				fn( $seconds ) => \max( \HOUR_IN_SECONDS, (int)$seconds ),
				$retentionByLevel
			),
			'high_value_events'           => \array_values( \array_unique( \array_filter(
				\array_map(
					fn( $event ) => \is_scalar( $event ) ? (string)$event : '',
					$highValueEvents
				),
				fn( string $event ) => !empty( $event ) && self::con()->comps->events->eventExists( $event )
			) ) ),
			'high_value_retention_seconds' => \max(
				\DAY_IN_SECONDS,
				(int)( $policy[ 'high_value_retention_seconds' ] ?? $defaults[ 'high_value_retention_seconds' ] )
			),
			'retention_seconds_by_event'  => \array_reduce(
				\array_keys( $retentionByEvent ),
				function ( array $carry, $event ) use ( $retentionByEvent ) :array {
					if ( \is_string( $event ) && !empty( $event ) ) {
						$carry[ $event ] = \max( \HOUR_IN_SECONDS, (int)$retentionByEvent[ $event ] );
					}
					return $carry;
				},
				[]
			),
		];
	}
}
