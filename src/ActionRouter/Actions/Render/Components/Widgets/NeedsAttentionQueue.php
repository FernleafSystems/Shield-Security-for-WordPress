<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Services\Services;

class NeedsAttentionQueue extends BaseRender {

	public const SLUG = 'render_widget_needs_attention_queue';
	public const TEMPLATE = '/wpadmin/components/widget/needs_attention_queue.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$scansCon = $con->comps->scans;
		$counter = $scansCon->getScanResultsCount();
		$scansResultsLink = $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS );

		$items = \array_values( \array_filter( [
			$this->buildItem(
				'scans',
				__( 'WP Files', 'wp-simple-firewall' ),
				$counter->countWPFiles(),
				'critical',
				sprintf(
					_n( '%s WordPress core file needs review.', '%s WordPress core files need review.', $counter->countWPFiles(), 'wp-simple-firewall' ),
					$counter->countWPFiles()
				),
				$scansResultsLink
			),
			$this->buildItem(
				'scans',
				__( 'Plugin Files', 'wp-simple-firewall' ),
				$counter->countPluginFiles(),
				'warning',
				sprintf(
					_n( '%s plugin file needs review.', '%s plugin files need review.', $counter->countPluginFiles(), 'wp-simple-firewall' ),
					$counter->countPluginFiles()
				),
				$scansResultsLink
			),
			$this->buildItem(
				'scans',
				__( 'Theme Files', 'wp-simple-firewall' ),
				$counter->countThemeFiles(),
				'warning',
				sprintf(
					_n( '%s theme file needs review.', '%s theme files need review.', $counter->countThemeFiles(), 'wp-simple-firewall' ),
					$counter->countThemeFiles()
				),
				$scansResultsLink
			),
			$scansCon->AFS()->isEnabledMalwareScanPHP()
				? $this->buildItem(
					'scans',
					__( 'Malware', 'wp-simple-firewall' ),
					$counter->countMalware(),
					'critical',
					sprintf(
						_n( '%s malware issue detected.', '%s malware issues detected.', $counter->countMalware(), 'wp-simple-firewall' ),
						$counter->countMalware()
					),
					$scansResultsLink
				)
				: null,
			$scansCon->WPV()->isEnabled()
				? $this->buildItem(
					'scans',
					__( 'Vulnerable Assets', 'wp-simple-firewall' ),
					$counter->countVulnerableAssets(),
					'critical',
					sprintf(
						_n( '%s vulnerable asset detected.', '%s vulnerable assets detected.', $counter->countVulnerableAssets(), 'wp-simple-firewall' ),
						$counter->countVulnerableAssets()
					),
					$scansResultsLink
				)
				: null,
			$scansCon->APC()->isEnabled()
				? $this->buildItem(
					'scans',
					__( 'Abandoned Assets', 'wp-simple-firewall' ),
					$counter->countAbandoned(),
					'warning',
					sprintf(
						_n( '%s abandoned asset detected.', '%s abandoned assets detected.', $counter->countAbandoned(), 'wp-simple-firewall' ),
						$counter->countAbandoned()
					),
					$scansResultsLink
				)
				: null,
		] ) );

		$zoneGroups = $this->buildZoneGroups( $items );
		$hasItems = !empty( $items );

		$latestScanAt = $this->getLatestCompletedScanTimestamp( $scansCon->getScanSlugs() );
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

	private function buildItem( string $zone, string $label, int $count, string $severity, string $description, string $href ) :?array {
		if ( $count <= 0 ) {
			return null;
		}

		return [
			'zone'        => $zone,
			'label'       => $label,
			'count'       => $count,
			'severity'    => $severity,
			'description' => $description,
			'href'        => $href,
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

	private function getLatestCompletedScanTimestamp( array $scanSlugs ) :int {
		$latest = 0;
		foreach ( $scanSlugs as $scanSlug ) {
			try {
				$record = self::con()
							  ->db_con
							  ->scans
							  ->getQuerySelector()
							  ->filterByScan( (string)$scanSlug )
							  ->filterByFinished()
							  ->setOrderBy( 'id', 'DESC', true )
							  ->first();
				if ( $record instanceof ScanRecord && (int)$record->finished_at > $latest ) {
					$latest = (int)$record->finished_at;
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $latest;
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
