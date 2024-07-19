<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Scans;

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
				'please_enable' => $con->plugin_urls->zone( Scans::Slug() ),
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