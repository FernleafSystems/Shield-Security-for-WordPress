<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type ConfigureZoneTileContract from \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder
 * @phpstan-import-type ConfigureRowContract from \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder
 * @phpstan-import-type ConfigureStatus from \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder
 * @phpstan-type SiteHealthStatus 'good'|'recommended'|'critical'
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

	private ConfigureZoneTilesBuilder $tilesBuilder;

	public function __construct( ?ConfigureZoneTilesBuilder $tilesBuilder = null ) {
		$this->tilesBuilder = $tilesBuilder ?? new ConfigureZoneTilesBuilder();
	}

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
		foreach ( $this->configureZoneTiles() as $tile ) {
			$zones[] = $this->buildZoneStatus( $tile );
		}
		return $zones;
	}

	/**
	 * @param ConfigureZoneTileContract $tile
	 * @return ZoneStatus
	 */
	private function buildZoneStatus( array $tile ) :array {
		$zoneSlug = sanitize_key( $tile[ 'key' ] );
		if ( $zoneSlug === '' ) {
			throw new \InvalidArgumentException( 'Shield security zone slug is empty.' );
		}

		$status = $this->siteHealthStatusForConfigureStatus( $tile[ 'status' ] );

		return [
			'slug'        => $zoneSlug,
			'title'       => $tile[ 'label' ],
			'status'      => $status,
			'status_label' => $this->statusLabel( $status ),
			'description' => $this->buildZoneDescription( $tile[ 'label' ], $status, $tile[ 'panel' ][ 'rows' ] ),
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
	 * @param list<ConfigureRowContract> $rows
	 */
	private function buildZoneDescription( string $zoneTitle, string $status, array $rows ) :string {
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
			fn( array $row ) :string => sprintf( '<li>%s</li>', esc_html( $this->rowSummary( $row ) ) ),
			$this->problemRows( $rows )
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
	 * @param list<ConfigureRowContract> $rows
	 * @return list<ConfigureRowContract>
	 */
	private function problemRows( array $rows ) :array {
		return \array_values( \array_filter(
			$rows,
			static fn( array $row ) :bool => \in_array( $row[ 'status' ], [ 'critical', 'warning' ], true )
		) );
	}

	/**
	 * @param ConfigureRowContract $row
	 */
	private function rowSummary( array $row ) :string {
		$title = \trim( $row[ 'title' ] );
		$firstExplanation = \count( $row[ 'explanations' ] ) > 0 ? \trim( (string)$row[ 'explanations' ][ 0 ] ) : '';
		$summary = $firstExplanation === '' ? \trim( $row[ 'note' ] ) : $firstExplanation;

		return $summary === '' ? $title : sprintf( '%s: %s', $title, $summary );
	}

	/**
	 * @return list<ConfigureZoneTileContract>
	 */
	private function configureZoneTiles() :array {
		return \array_values( \array_filter(
			$this->tilesBuilder->build(),
			static fn( array $tile ) :bool => $tile[ 'include_in_posture' ] === true
		) );
	}

	/**
	 * @param ConfigureStatus $status
	 * @return SiteHealthStatus
	 */
	private function siteHealthStatusForConfigureStatus( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return 'critical';
			case 'warning':
				return 'recommended';
			case 'good':
			default:
				return 'good';
		}
	}
}
