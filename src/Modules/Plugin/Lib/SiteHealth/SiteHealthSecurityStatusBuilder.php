<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZoneSignals;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

/**
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
 */
class SiteHealthSecurityStatusBuilder {

	use PluginControllerConsumer;

	public const TEST_KEY_PREFIX = 'shield_security_';

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
	public function buildTests() :array {
		$tests = [];
		foreach ( $this->zoneTitles() as $zoneSlug => $zoneTitle ) {
			$testKey = $this->testKeyForZone( $zoneSlug );
			$tests[ $testKey ] = [
				'label'     => $zoneTitle,
				'test'      => fn() :array => $this->buildZoneResult( $zoneSlug ),
				'skip_cron' => true,
			];
		}
		return $tests;
	}

	/**
	 * @return array{
	 *   label:string,
	 *   status:'good'|'recommended'|'critical',
	 *   badge:array{label:string,color:string},
	 *   description:string,
	 *   actions:string,
	 *   test:string
	 * }
	 */
	public function buildZoneResult( string $zoneSlug ) :array {
		$zoneSlug = sanitize_key( $zoneSlug );
		$zoneTitle = $this->zoneTitles()[ $zoneSlug ] ?? null;
		if ( $zoneTitle === null ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown Shield security zone: %s', $zoneSlug ) );
		}

		$signals = $this->signalsByZone()[ $zoneSlug ] ?? [];
		$status = $this->statusForSignals( $signals );

		return [
			'label'       => $zoneTitle,
			'status'      => $status,
			'badge'       => [
				'label' => __( 'Security', 'wp-simple-firewall' ),
				'color' => 'blue',
			],
			'description' => $this->buildDescription( $zoneTitle, $status, $signals ),
			'actions'     => $this->buildActions( $zoneSlug ),
			'test'        => $this->testKeyForZone( $zoneSlug ),
		];
	}

	private function testKeyForZone( string $zoneSlug ) :string {
		return self::TEST_KEY_PREFIX.sanitize_key( $zoneSlug );
	}

	/**
	 * @param list<ZoneSignal> $signals
	 * @return 'good'|'recommended'|'critical'
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

	/**
	 * @param 'good'|'recommended'|'critical' $status
	 * @param list<ZoneSignal>                $signals
	 */
	private function buildDescription( string $zoneTitle, string $status, array $signals ) :string {
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

	private function buildActions( string $zoneSlug ) :string {
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
