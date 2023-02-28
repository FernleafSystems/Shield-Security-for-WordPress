<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class AlertFileLocker extends BaseBuilderForScans {

	public const SLUG = 'alert_file_locker';
	public const TEMPLATE = '/components/reports/components/alert_filelocker.twig';

	protected function getRenderData() :array {
		$locksLoader = new LoadFileLocks();
		$hasNotNotified = count( $locksLoader->withProblemsNotNotified() ) > 0;
		if ( $hasNotNotified ) {
			$this->markAlertsAsNotified();
		}

		return [
			'flags'   => [
				'render_required' => $hasNotNotified,
			],
			'hrefs'   => [
				'view_results' => $this->getCon()->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'title'        => __( 'File Locker Changes Detected', 'wp-simple-firewall' ),
				'file_changed' => __( 'Changes have been detected in the contents of critical files.', 'wp-simple-firewall' ),
				'total_files'  => sprintf( '%s: %s', __( 'Total Changed Files', 'wp-simple-firewall' ), count( $locksLoader->withProblems() ) ),
				'view_results' => __( 'Click Here To View File Locker Results', 'wp-simple-firewall' ),
			],
			'vars'    => [
//				'count' => $this->action_data[ 'count_with_problems' ]
			],
		];
	}

	private function markAlertsAsNotified() {
		$locksLoader = new LoadFileLocks();
		/** @var FileLockerDB\Update $updater */
		$updater = $this->getCon()
						->getModule_HackGuard()
						->getDbH_FileLocker()
						->getQueryUpdater();
		foreach ( $locksLoader->withProblemsNotNotified() as $record ) {
			$updater->markNotified( $record );
		}

		$locksLoader->clearLocksCache();
	}
}