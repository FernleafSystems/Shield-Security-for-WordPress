<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansFileLockerAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class FileLocker extends Actions\Render\Components\Scans\BaseScans {

	public const SLUG = 'scanresults_filelocker';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/realtime/file_locker/index.twig';

	protected function getRenderData() :array {
		$con = $this->con();
		$mod = $con->getModule_HackGuard();

		$lockerCon = $mod->getFileLocker();
		$problemLocks = ( new LoadFileLocks() )->withProblems();

		return [
			'ajax'    => [
				'filelocker_showdiff'   => ScansFileLockerDiff::SLUG,
				'filelocker_fileaction' => ActionData::BuildJson( ScansFileLockerAction::class ),
			],
			'flags'   => [
				'is_enabled'    => $lockerCon->isEnabled(),
				'is_restricted' => !$this->con()->isPremiumActive(),
			],
			'hrefs'   => [
				'options'       => $con->plugin_urls->modCfgOption( 'file_locker' ),
				'please_enable' => $con->plugin_urls->modCfgOption( 'file_locker' ),
			],
			'vars'    => [
				'file_locks' => [
					'good'        => ( new LoadFileLocks() )->withoutProblems(),
					'bad'         => $problemLocks,
					'count_items' => \count( $problemLocks ),
				],
			],
			'strings' => [
				'please_select' => __( 'Please select a file to review.', 'wp-simple-firewall' ),
			],
			'count'   => \count( $problemLocks )
		];
	}
}