<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Services\Services;

class ReportAreaScansCurrentResults extends ReportAreaBase {

	public const SLUG = 'report_area_scans_current_results';
	public const TEMPLATE = '/reports/areas/scans/current_scan_results.twig';

	protected function getRenderData() :array {

		$totalFiles = 0;

		$scanCounts = $this->getCounts();
		foreach ( $scanCounts as $slug => &$scanCount ) {
			if ( $scanCount[ 'available' ] ) {
				$scanCount[ 'slug' ] = $slug;
				$scanCount[ 'has_count' ] = $scanCount[ 'count' ] > 0;
				$scanCount[ 'colour' ] = $scanCount[ 'count' ] > 0 ? 'warning' : 'success';
				$totalFiles += $scanCount[ 'count' ];
			}
			else {
				unset( $scanCounts[ $slug ] );
			}
		}

		// Ensure items with higher counts are ordered first.
		\usort( $scanCounts, function ( $a, $b ) {
			$countA = $a[ 'count' ];
			$countB = $b[ 'count' ];
			return $countA == $countB ? 0 : ( ( $countA > $countB ) ? -1 : 1 );
		} );

		return [
			'flags'   => [
				'has_counts' => $totalFiles > 0,
			],
			'hrefs'   => [
				'view_scan_results' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'view_scan_results' => __( 'View Scan Results In-Full', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'scan_counts'     => $scanCounts,
				'generation_date' => Services::WpGeneral()->getTimeStringForDisplay(),
			],
		];
	}

	private function getCounts() :array {
		$c = new Counts( RetrieveCount::CONTEXT_ACTIVE_PROBLEMS );
		$scansCon = self::con()->getModule_HackGuard()->getScansCon();
		return [
			'file_locker'             => [
				'name'      => 'File Locker',
				'count'     => \count( ( new LoadFileLocks() )->withProblemsNotNotified() ),
				'available' => self::con()->getModule_HackGuard()->getFileLocker()->isEnabled(),
			],
			Wpv::SCAN_SLUG            => [
				'name'      => $scansCon->WPV()->getScanName(),
				'count'     => $c->countVulnerableAssets(),
				'available' => $scansCon->WPV()->isEnabled(),
			],
			Apc::SCAN_SLUG            => [
				'name'      => $scansCon->APC()->getScanName(),
				'count'     => $c->countAbandoned(),
				'available' => $scansCon->APC()->isEnabled(),
			],
			Afs::SCAN_SLUG.'_malware' => [
				'name'      => __( 'Potential Malware' ),
				'count'     => $c->countMalware(),
				'available' => $scansCon->AFS()->isEnabledMalwareScanPHP(),
			],
			Afs::SCAN_SLUG.'_wp'      => [
				'name'      => __( 'WordPress Files' ),
				'count'     => $c->countWPFiles(),
				'available' => $scansCon->AFS()->isScanEnabledWpCore(),
			],
			Afs::SCAN_SLUG.'_plugin'  => [
				'name'      => __( 'Plugin Files' ),
				'count'     => $c->countPluginFiles(),
				'available' => $scansCon->AFS()->isScanEnabledPlugins(),
			],
			Afs::SCAN_SLUG.'_theme'   => [
				'name'      => __( 'Theme Files' ),
				'count'     => $c->countThemeFiles(),
				'available' => $scansCon->AFS()->isScanEnabledThemes(),
			],
		];
	}
}