<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

class NeedsAttentionQueuePayload {

	/**
	 * @param array{
	 *   has_items?:bool,
	 *   total_items?:int,
	 *   severity?:string,
	 *   icon_class?:string,
	 *   subtext?:string
	 * } $defaults
	 * @return array{
	 *   has_items:bool,
	 *   total_items:int,
	 *   severity:string,
	 *   icon_class:string,
	 *   subtext:string
	 * }
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
	 * @return list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }>
	 */
	public static function zoneGroups( array $payload ) :array {
		$zoneGroups = $payload[ 'render_data' ][ 'vars' ][ 'zone_groups' ] ?? [];
		if ( !\is_array( $zoneGroups ) ) {
			return [];
		}

		return \array_values( \array_filter( \array_map( static function ( $group ) {
			if ( !\is_array( $group ) ) {
				return null;
			}
			return [
				'slug'         => (string)( $group[ 'slug' ] ?? '' ),
				'label'        => (string)( $group[ 'label' ] ?? '' ),
				'icon_class'   => (string)( $group[ 'icon_class' ] ?? '' ),
				'severity'     => (string)( $group[ 'severity' ] ?? 'good' ),
				'total_issues' => \max( 0, (int)( $group[ 'total_issues' ] ?? 0 ) ),
				'items'        => \is_array( $group[ 'items' ] ?? null ) ? \array_values( $group[ 'items' ] ) : [],
			];
		}, $zoneGroups ) ) );
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
	 * @param list<array{severity?:string,count?:int}> $items
	 * @return array{critical:int,warning:int}
	 */
	public static function countsFromItems( array $items ) :array {
		$counts = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $items as $item ) {
			$severity = (string)( $item[ 'severity' ] ?? '' );
			if ( isset( $counts[ $severity ] ) ) {
				$counts[ $severity ] += \max( 0, (int)( $item[ 'count' ] ?? 0 ) );
			}
		}
		return $counts;
	}

	/**
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }> $zoneGroups
	 * @return array{critical:int,warning:int}
	 */
	public static function countsFromZoneGroups( array $zoneGroups ) :array {
		$totals = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $zoneGroups as $group ) {
			$counts = self::countsFromItems( \is_array( $group[ 'items' ] ?? null ) ? $group[ 'items' ] : [] );
			$totals[ 'critical' ] += $counts[ 'critical' ];
			$totals[ 'warning' ] += $counts[ 'warning' ];
		}
		return $totals;
	}
}

