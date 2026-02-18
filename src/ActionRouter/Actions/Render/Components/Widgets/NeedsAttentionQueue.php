<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Services\Services;

class NeedsAttentionQueue extends BaseRender {

	public const SLUG = 'render_widget_needs_attention_queue';
	public const TEMPLATE = '/wpadmin/components/widget/needs_attention_queue.twig';

	protected function getRenderData() :array {
		$scansCon = self::con()->comps->scans;
		$provider = new AttentionItemsProvider();
		$items = $provider->buildQueueItems();

		$zoneGroups = $this->buildZoneGroups( $items );
		$hasItems = !empty( $items );

		$latestScanAt = $provider->getLatestCompletedScanTimestamp( $scansCon->getScanSlugs() );
		$lastScanSubtext = $latestScanAt > 0
			? sprintf(
				__( 'Last completed scan: %s', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
			)
			: '';

		return [
			'flags'   => [
				'has_items' => $hasItems,
			],
			'strings' => [
				'title'             => __( 'Needs Attention', 'wp-simple-firewall' ),
				'issues_found'      => __( 'Issues Found', 'wp-simple-firewall' ),
				'all_clear'         => __( 'All Clear', 'wp-simple-firewall' ),
				'all_clear_message' => __( 'No scan-based issues currently need your attention.', 'wp-simple-firewall' ),
				'last_scan_subtext' => $lastScanSubtext,
			],
			'vars'    => [
				'overall_severity' => $this->determineOverallSeverity( $items ),
				'total_items'      => \array_sum( \array_column( $items, 'count' ) ),
				'zone_groups'      => \array_values( $zoneGroups ),
				'zone_chips'       => $this->buildAllClearZoneChips(),
			],
		];
	}

	private function buildZoneGroups( array $items ) :array {
		$zoneLabels = $this->getZoneLabels();
		$groups = [];

		foreach ( $items as $item ) {
			$zone = (string)$item[ 'zone' ];
			if ( !isset( $groups[ $zone ] ) ) {
				$groups[ $zone ] = [
					'slug'         => $zone,
					'label'        => $zoneLabels[ $zone ] ?? $zone,
					'severity'     => 'good',
					'total_issues' => 0,
					'items'        => [],
				];
			}
			$groups[ $zone ][ 'items' ][] = $item;
			$groups[ $zone ][ 'total_issues' ] += (int)$item[ 'count' ];
			$groups[ $zone ][ 'severity' ] = $this->maxSeverity( [
				$groups[ $zone ][ 'severity' ],
				(string)$item[ 'severity' ],
			] );
		}

		return $groups;
	}

	private function determineOverallSeverity( array $items ) :string {
		if ( empty( $items ) ) {
			return 'good';
		}
		return $this->maxSeverity( \array_column( $items, 'severity' ) );
	}

	private function maxSeverity( array $severities ) :string {
		$rankMap = [
			'good'     => 0,
			'warning'  => 1,
			'critical' => 2,
		];
		$highest = 'good';
		foreach ( $severities as $severity ) {
			$severity = (string)$severity;
			if ( ( $rankMap[ $severity ] ?? -1 ) > ( $rankMap[ $highest ] ?? -1 ) ) {
				$highest = $severity;
			}
		}
		return $highest;
	}

	private function buildAllClearZoneChips() :array {
		$chips = [];
		foreach ( [
			'scans',
			'firewall',
			'ips',
			'login',
			'users',
			'spam',
			'headers',
			'secadmin',
		] as $zone ) {
			$chips[] = [
				'slug'     => $zone,
				'label'    => $this->getZoneLabels()[ $zone ] ?? $zone,
				'severity' => 'good',
			];
		}
		return $chips;
	}

	private function getZoneLabels() :array {
		return [
			'scans'    => __( 'Scans', 'wp-simple-firewall' ),
			'firewall' => __( 'Firewall', 'wp-simple-firewall' ),
			'ips'      => __( 'IPs', 'wp-simple-firewall' ),
			'login'    => __( 'Login', 'wp-simple-firewall' ),
			'users'    => __( 'Users', 'wp-simple-firewall' ),
			'spam'     => __( 'Spam', 'wp-simple-firewall' ),
			'headers'  => __( 'Headers', 'wp-simple-firewall' ),
			'secadmin' => __( 'SecAdmin', 'wp-simple-firewall' ),
		];
	}
}
