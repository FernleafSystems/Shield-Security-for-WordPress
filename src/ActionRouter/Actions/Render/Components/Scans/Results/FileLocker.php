<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Scans;

class FileLocker extends Base {

	public const SLUG = 'scanresults_filelocker';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/realtime/file_locker/index.twig';

	protected function getRenderTemplate() :string {
		return $this->isActionsQueueDisplayContext()
			? '/wpadmin_pages/insights/scans/results/actions_queue_asset_cards.twig'
			: parent::getRenderTemplate();
	}

	protected function getRenderData() :array {
		if ( $this->isActionsQueueDisplayContext() ) {
			$pane = ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder() )
				->buildActionsQueueFileLockerPane();

			return [
				'flags'   => [
					'is_disabled' => $pane[ 'is_disabled' ],
				],
				'strings' => [
					'no_issues'         => __( 'No File Locker items require review.', 'wp-simple-firewall' ),
					'disabled_message'  => $pane[ 'disabled_message' ],
					'select_asset_hint' => __( 'Select a locked file above to review its current details.', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'asset_cards' => $pane[ 'cards' ],
					'count_items' => \count( $pane[ 'cards' ] ),
				],
			];
		}

		$con = self::con();
		$problemLocks = ( new LoadFileLocks() )->withProblems();
		return [
			'flags'   => [
				'is_enabled'    => $con->comps->file_locker->isEnabled(),
				'is_restricted' => !$con->isPremiumActive(),
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
