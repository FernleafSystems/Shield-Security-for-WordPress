<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\ScansFileLockerAction;

class FileLocker extends Actions\Render\Components\Scans\BaseScans {

	public const SLUG = 'scanresults_filelocker';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/realtime/file_locker/index.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->primary_mod;

		$lockerCon = $mod->getFileLocker();
		$lockLoader = ( new LoadFileLocks() )->setMod( $mod );
		$problemLocks = $lockLoader->withProblems();
		$goodLocks = $lockLoader->withoutProblems();

		return [
			'ajax'    => [
				'filelocker_showdiff'   => ScansFileLockerDiff::SLUG,
				'filelocker_fileaction' => ActionData::BuildJson( ScansFileLockerAction::SLUG ),
			],
			'flags'   => [
				'is_enabled'    => $lockerCon->isEnabled(),
				'is_restricted' => !$this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'options'       => $con->plugin_urls->modOption( $mod, 'file_locker' ),
				'please_enable' => $con->plugin_urls->modOption( $mod, 'file_locker' ),
			],
			'vars'    => [
				'file_locks' => [
					'good'        => $goodLocks,
					'bad'         => $problemLocks,
					'count_items' => count( $problemLocks ),
				],
			],
			'strings' => [
				'title'         => __( 'File Locker', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Results of file locker monitoring', 'wp-simple-firewall' ),
				'please_select' => __( 'Please select a file to review.', 'wp-simple-firewall' ),
			],
			'count'   => count( $problemLocks )
		];
	}
}