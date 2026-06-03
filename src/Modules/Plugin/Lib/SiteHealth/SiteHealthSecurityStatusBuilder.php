<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZoneSignals;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

/**
 * @phpstan-type SiteHealthStatus 'good'|'recommended'|'critical'
 * @phpstan-type ZoneSignal array{
 *   slug:string,
 *   title:string,
 *   weight:int,
 *   score:int,
 *   is_protected:bool,
 *   severity:'good'|'warning'|'critical',
 *   explanation:list<string>,
 *   config_action:array<string,mixed>,
 *   zone:string
 * }
 * @phpstan-type SiteHealthResult array{
 *   label:string,
 *   status:SiteHealthStatus,
 *   badge:array{label:string,color:string},
 *   description:string,
 *   actions:string,
 *   test:string
 * }
 * @phpstan-type ZoneStatus array{
 *   slug:string,
 *   title:string,
 *   status:SiteHealthStatus,
 *   status_label:string,
 *   description:string,
 *   actions:string,
 *   panel_id:string
 * }
 * @phpstan-type TabGroup array{
 *   status:SiteHealthStatus,
 *   title:string,
 *   description:string,
 *   items:list<ZoneStatus>
 * }
 */
class SiteHealthSecurityStatusBuilder {

	use PluginControllerConsumer;

	public const TEST_KEY = 'shield_security';
	public const TAB_SLUG = 'shield_security';

	/**
	 * @var ?array<string,list<ZoneSignal>>
	 */
	private ?array $signalsByZone = null;

	/**
	 * @var ?array<string,string>
	 */
	private ?array $zoneTitles = null;

	/**
	 * @return array<string,array{label:string,test:callable,skip_cron:bool}>
	 */
	public function buildTests( string $detailsUrl ) :array {
		return [
			self::TEST_KEY => [
				'label'     => __( 'Shield Security', 'wp-simple-firewall' ),
				'test'      => fn() :array => $this->buildAggregateResult( $detailsUrl ),
				'skip_cron' => true,
			],
		];
	}

	/**
	 * @return SiteHealthResult
	 */
	public function buildAggregateResult( string $detailsUrl ) :array {
		$zoneStatuses = $this->buildZoneStatuses();
		$status = $this->aggregateStatusForZones( $zoneStatuses );
		return [
			'label'       => __( 'Shield Security', 'wp-simple-firewall' ),
			'status'      => $status,
			'badge'       => [
				'label' => __( 'Security', 'wp-simple-firewall' ),
				'color' => 'blue',
			],
			'description' => $this->buildAggregateDescription( $status, $zoneStatuses ),
			'actions'     => $this->buildAggregateActions( $detailsUrl ),
			'test'        => self::TEST_KEY,
		];
	}

	/**
	 * @return array{critical:TabGroup,recommended:TabGroup,good:TabGroup}
	 */
	public function buildTabGroups() :array {
		$groups = [
			'critical'    => $this->newTabGroup(
				'critical',
				__( 'Critical security areas', 'wp-simple-firewall' ),
				__( 'These Shield security areas need attention as soon as possible.', 'wp-simple-firewall' )
			),
			'recommended' => $this->newTabGroup(
				'recommended',
				__( 'Recommended security improvements', 'wp-simple-firewall' ),
				__( 'These Shield security areas can be improved.', 'wp-simple-firewall' )
			),
			'good'        => $this->newTabGroup(
				'good',
				__( 'Passed security areas', 'wp-simple-firewall' ),
				__( 'These Shield security areas are currently reporting no high-level issues.', 'wp-simple-firewall' )
			),
		];

		foreach ( $this->buildZoneStatuses() as $zoneStatus ) {
			$groups[ $zoneStatus[ 'status' ] ][ 'items' ][] = $zoneStatus;
		}

		return $groups;
	}

	/**
	 * @param SiteHealthStatus $status
	 * @return TabGroup
	 */
	private function newTabGroup( string $status, string $title, string $description ) :array {
		return [
			'status'      => $status,
			'title'       => $title,
			'description' => $description,
			'items'       => [],
		];
	}

	/**
	 * @return list<ZoneStatus>
	 */
	public function buildZoneStatuses() :array {
		$zones = [];
		foreach ( $this->zoneTitles() as $zoneSlug => $zoneTitle ) {
			$zones[] = $this->buildZoneStatus( $zoneSlug, $zoneTitle );
		}
		return $zones;
	}

	/**
	 * @return ZoneStatus
	 */
	private function buildZoneStatus( string $zoneSlug, string $zoneTitle ) :array {
		$zoneSlug = sanitize_key( $zoneSlug );
		if ( $zoneSlug === '' ) {
			throw new \InvalidArgumentException( 'Shield security zone slug is empty.' );
		}

		$signals = $this->signalsByZone()[ $zoneSlug ] ?? [];
		$status = $this->statusForSignals( $signals );

		return [
			'slug'        => $zoneSlug,
			'title'       => $zoneTitle,
			'status'      => $status,
			'status_label' => $this->statusLabel( $status ),
			'description' => $this->buildZoneDescription( $zoneTitle, $status, $signals ),
			'actions'     => $this->buildZoneActions( $zoneSlug ),
			'panel_id'    => 'health-check-accordion-block-shield_'.$zoneSlug,
		];
	}

	/**
	 * @param list<ZoneStatus> $zones
	 * @return SiteHealthStatus
	 */
	private function aggregateStatusForZones( array $zones ) :string {
		foreach ( $zones as $zone ) {
			if ( $zone[ 'status' ] === 'critical' ) {
				return 'critical';
			}
		}

		foreach ( $zones as $zone ) {
			if ( $zone[ 'status' ] === 'recommended' ) {
				return 'recommended';
			}
		}

		return 'good';
	}

	/**
	 * @param list<ZoneSignal> $signals
	 * @return SiteHealthStatus
	 */
	private function statusForSignals( array $signals ) :string {
		foreach ( $signals as $signal ) {
			if ( $signal[ 'severity' ] === 'critical' ) {
				return 'critical';
			}
		}

		foreach ( $signals as $signal ) {
			if ( $signal[ 'severity' ] === 'warning' || !$signal[ 'is_protected' ] ) {
				return 'recommended';
			}
		}

		return 'good';
	}

	private function statusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'recommended':
				return __( 'Recommended', 'wp-simple-firewall' );
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	/**
	 * @param SiteHealthStatus $status
	 * @param list<ZoneStatus> $zones
	 */
	private function buildAggregateDescription( string $status, array $zones ) :string {
		if ( $status === 'good' ) {
			return sprintf(
				'<p>%s</p>',
				esc_html( __( 'Shield reports no high-level security issues across its security zones.', 'wp-simple-firewall' ) )
			);
		}

		$countIssues = \count( \array_filter(
			$zones,
			static fn( array $zone ) :bool => $zone[ 'status' ] !== 'good'
		) );

		return sprintf(
			'<p>%s</p>',
			esc_html( sprintf(
				_n(
					'Shield found %s security area that needs attention.',
					'Shield found %s security areas that need attention.',
					$countIssues,
					'wp-simple-firewall'
				),
				(string)$countIssues
			) )
		);
	}

	private function buildAggregateActions( string $detailsUrl ) :string {
		return sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( $detailsUrl ),
			esc_html( __( 'Review Shield security details', 'wp-simple-firewall' ) )
		);
	}

	/**
	 * @param SiteHealthStatus $status
	 * @param list<ZoneSignal> $signals
	 */
	private function buildZoneDescription( string $zoneTitle, string $status, array $signals ) :string {
		if ( $status === 'good' ) {
			return sprintf(
				'<p>%s</p>',
				esc_html( sprintf(
					__( 'Shield reports no high-level security issues for %s.', 'wp-simple-firewall' ),
					$zoneTitle
				) )
			);
		}

		$items = \array_map(
			fn( array $signal ) :string => sprintf( '<li>%s</li>', esc_html( $this->signalSummary( $signal ) ) ),
			$this->problemSignals( $signals )
		);

		return sprintf(
			'<p>%s</p><ul>%s</ul>',
			esc_html( sprintf(
				__( 'Shield found high-level security items that need attention for %s.', 'wp-simple-firewall' ),
				$zoneTitle
			) ),
			\implode( '', $items )
		);
	}

	private function buildZoneActions( string $zoneSlug ) :string {
		return sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( self::con()->plugin_urls->zone( $zoneSlug ) ),
			esc_html( __( 'Review Shield security settings', 'wp-simple-firewall' ) )
		);
	}

	/**
	 * @param list<ZoneSignal> $signals
	 * @return list<ZoneSignal>
	 */
	private function problemSignals( array $signals ) :array {
		return \array_values( \array_filter(
			$signals,
			static fn( array $signal ) :bool => $signal[ 'severity' ] !== 'good' || !$signal[ 'is_protected' ]
		) );
	}

	/**
	 * @param ZoneSignal $signal
	 */
	private function signalSummary( array $signal ) :string {
		$title = \trim( $signal[ 'title' ] );
		$firstExplanation = \trim( (string)( $signal[ 'explanation' ][ 0 ] ?? '' ) );

		return $firstExplanation === '' ? $title : sprintf( '%s: %s', $title, $firstExplanation );
	}

	/**
	 * @return array<string,list<ZoneSignal>>
	 */
	private function signalsByZone() :array {
		if ( $this->signalsByZone === null ) {
			$this->signalsByZone = [];
			foreach ( $this->buildZoneSignals() as $signal ) {
				$zoneSlug = sanitize_key( $signal[ 'zone' ] );
				if ( $zoneSlug !== '' ) {
					$this->signalsByZone[ $zoneSlug ][] = $signal;
				}
			}
		}
		return $this->signalsByZone;
	}

	/**
	 * @return array<string,string>
	 */
	protected function zoneTitles() :array {
		if ( $this->zoneTitles === null ) {
			$this->zoneTitles = [];
			foreach ( ( new SecurityZonesCon() )->getZones() as $zone ) {
				$this->zoneTitles[ $zone::Slug() ] = $zone->title();
			}
		}
		return $this->zoneTitles;
	}

	/**
	 * @return list<ZoneSignal>
	 */
	protected function buildZoneSignals() :array {
		return ( new BuildZoneSignals() )->build();
	}
}
