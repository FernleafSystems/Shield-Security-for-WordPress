<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class FileLocker extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans {

	public const SLUG = 'scanresults_filelocker';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/realtime/file_locker/index.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$problemLocks = ( new LoadFileLocks() )->withProblems();
		return [
			'flags'   => [
				'is_enabled'    => $con->comps->file_locker->isEnabled(),
				'is_restricted' => !self::con()->isPremiumActive(),
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