<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Apc;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Wpv;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;

class BuildForScans extends BuildBase {

	public function build() :array {
		return [
			'new'     => $this->buildForContext( RetrieveCount::CONTEXT_NOT_YET_NOTIFIED ),
			'current' => $this->buildForContext( RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ),
		];
	}

	private function buildForContext( int $context ) :array {
		$scansCon = self::con()->getModule_HackGuard()->getScansCon();
		$c = new Counts( $context );
		$scanCounts = [
			'file_locker'             => [
				'name'      => 'File Locker',
				'count'     => $context === RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ?
					\count( ( new LoadFileLocks() )->withProblems() )
					: \count( ( new LoadFileLocks() )->withProblemsNotNotified() ),
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

		foreach ( $scanCounts as $slug => &$scanCount ) {
			if ( $scanCount[ 'available' ] ) {
				$scanCount[ 'slug' ] = $slug;
				$scanCount[ 'has_count' ] = $scanCount[ 'count' ] > 0;
				$scanCount[ 'colour' ] = $scanCount[ 'count' ] > 0 ? 'warning' : 'success';
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

		return $scanCounts;
	}
}