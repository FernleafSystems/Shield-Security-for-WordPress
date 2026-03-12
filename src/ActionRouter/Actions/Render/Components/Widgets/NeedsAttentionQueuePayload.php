<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

/**
 * @phpstan-type QueueSummary array{
 *   has_items:bool,
 *   total_items:int,
 *   severity:string,
 *   icon_class:string,
 *   subtext:string
 * }
 * @phpstan-type QueueItem array{
 *   key:string,
 *   zone:string,
 *   label:string,
 *   count:int,
 *   severity:string,
 *   description:string,
 *   href:string,
 *   action:string,
 *   target:string
 * }
 * @phpstan-type ZoneGroup array{
 *   slug:string,
 *   label:string,
 *   icon_class:string,
 *   severity:string,
 *   total_issues:int,
 *   items:list<QueueItem>
 * }
 */
class NeedsAttentionQueuePayload {

	/**
	 * @param array{
	 *   has_items?:bool,
	 *   total_items?:int,
	 *   severity?:string,
	 *   icon_class?:string,
	 *   subtext?:string
	 * } $defaults
	 * @return QueueSummary
	 */
	public static function summary( array $payload, array $defaults = [] ) :array {
		$summary = $payload[ 'render_data' ][ 'vars' ][ 'summary' ] ?? [];
		if ( !\is_array( $summary ) ) {
			$summary = [];
		}

		$defaults = \array_merge( [
			'has_items'   => false,
			'total_items' => 0,
			'severity'    => 'good',
			'icon_class'  => '',
			'subtext'     => '',
		], $defaults );

		return [
			'has_items'   => (bool)( $summary[ 'has_items' ] ?? $defaults[ 'has_items' ] ),
			'total_items' => \max( 0, (int)( $summary[ 'total_items' ] ?? $defaults[ 'total_items' ] ) ),
			'severity'    => (string)( $summary[ 'severity' ] ?? $defaults[ 'severity' ] ),
			'icon_class'  => (string)( $summary[ 'icon_class' ] ?? $defaults[ 'icon_class' ] ),
			'subtext'     => (string)( $summary[ 'subtext' ] ?? $defaults[ 'subtext' ] ),
		];
	}

	/**
	 * @return list<ZoneGroup>
	 */
	public static function zoneGroups( array $payload ) :array {
		$zoneGroups = $payload[ 'render_data' ][ 'vars' ][ 'zone_groups' ] ?? [];
		if ( !\is_array( $zoneGroups ) ) {
			return [];
		}

		return \array_values( \array_filter( \array_map( static function ( $group ) :?array {
			return \is_array( $group )
				? self::normalizeZoneGroup( $group )
				: null;
		}, $zoneGroups ) ) );
	}

	/**
	 * @return ZoneGroup
	 */
	public static function zoneGroup( array $payload, string $slug ) :array {
		$normalizedSlug = self::normalizeSlug( $slug );
		foreach ( self::zoneGroups( $payload ) as $zoneGroup ) {
			if ( self::normalizeSlug( $zoneGroup[ 'slug' ] ) === $normalizedSlug ) {
				return $zoneGroup;
			}
		}

		return self::emptyZoneGroup( $normalizedSlug );
	}

	/**
	 * @param array<string,string> $defaults
	 * @return array<string,string>
	 */
	public static function strings( array $payload, array $defaults = [] ) :array {
		$strings = $payload[ 'render_data' ][ 'strings' ] ?? [];
		if ( !\is_array( $strings ) ) {
			$strings = [];
		}

		$normalized = [];
		foreach ( $defaults as $key => $default ) {
			$normalized[ $key ] = (string)( $strings[ $key ] ?? $default );
		}
		return $normalized;
	}

	/**
	 * @param list<QueueItem> $items
	 * @return array{critical:int,warning:int}
	 */
	public static function countsFromItems( array $items ) :array {
		$counts = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $items as $item ) {
			$severity = $item[ 'severity' ];
			if ( isset( $counts[ $severity ] ) ) {
				$counts[ $severity ] += $item[ 'count' ];
			}
		}
		return $counts;
	}

	/**
	 * @param list<ZoneGroup> $zoneGroups
	 * @return array{critical:int,warning:int}
	 */
	public static function countsFromZoneGroups( array $zoneGroups ) :array {
		$totals = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $zoneGroups as $group ) {
			$counts = self::countsFromItems( $group[ 'items' ] );
			$totals[ 'critical' ] += $counts[ 'critical' ];
			$totals[ 'warning' ] += $counts[ 'warning' ];
		}
		return $totals;
	}

	/**
	 * @return ZoneGroup
	 */
	private static function normalizeZoneGroup( array $group ) :array {
		$items = [];
		foreach ( \is_array( $group[ 'items' ] ?? null ) ? \array_values( $group[ 'items' ] ) : [] as $item ) {
			if ( \is_array( $item ) ) {
				$items[] = self::normalizeQueueItem( $item );
			}
		}

		return [
			'slug'         => (string)( $group[ 'slug' ] ?? '' ),
			'label'        => (string)( $group[ 'label' ] ?? '' ),
			'icon_class'   => (string)( $group[ 'icon_class' ] ?? '' ),
			'severity'     => (string)( $group[ 'severity' ] ?? 'good' ),
			'total_issues' => \max( 0, (int)( $group[ 'total_issues' ] ?? 0 ) ),
			'items'        => $items,
		];
	}

	/**
	 * @return QueueItem
	 */
	private static function normalizeQueueItem( array $item ) :array {
		return [
			'key'         => (string)( $item[ 'key' ] ?? '' ),
			'zone'        => (string)( $item[ 'zone' ] ?? '' ),
			'label'       => (string)( $item[ 'label' ] ?? '' ),
			'count'       => \max( 0, (int)( $item[ 'count' ] ?? 0 ) ),
			'severity'    => (string)( $item[ 'severity' ] ?? 'good' ),
			'description' => (string)( $item[ 'description' ] ?? '' ),
			'href'        => (string)( $item[ 'href' ] ?? '' ),
			'action'      => (string)( $item[ 'action' ] ?? '' ),
			'target'      => (string)( $item[ 'target' ] ?? '' ),
		];
	}

	/**
	 * @return ZoneGroup
	 */
	private static function emptyZoneGroup( string $slug ) :array {
		return [
			'slug'         => $slug,
			'label'        => '',
			'icon_class'   => '',
			'severity'     => 'good',
			'total_issues' => 0,
			'items'        => [],
		];
	}

	private static function normalizeSlug( string $slug ) :string {
		return \strtolower( \trim( $slug ) );
	}
}
