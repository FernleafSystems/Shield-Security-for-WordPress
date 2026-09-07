<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder;

class FileLocker extends Base {

	public const SLUG = 'scanresults_filelocker';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/actions_queue_asset_cards.twig';

	protected function getRenderData() :array {
		$pane = ( new ScansResultsViewBuilder() )->buildActionsQueueFileLockerPane();

		return [
			'flags'   => [
				'is_disabled' => $pane[ 'is_disabled' ],
				'is_file_locker' => true,
			],
			'strings' => [
				'no_issues'         => __( 'No File Locker entries are currently available to review.', 'wp-simple-firewall' ),
				'disabled_message'  => $pane[ 'disabled_message' ],
				'select_asset_hint' => __( 'Select a file above to review its current status and details.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'asset_cards'      => $pane[ 'cards' ],
				'count_items'      => \count( $pane[ 'cards' ] ),
				'disabled_actions' => $pane[ 'disabled_actions' ],
			],
		];
	}
}
