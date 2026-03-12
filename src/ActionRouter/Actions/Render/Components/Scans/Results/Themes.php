<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Services\Services;

class Themes extends PluginThemesBase {

	public const SLUG = 'scanresults_themes';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig';

	protected function getRenderTemplate() :string {
		return $this->isActionsQueueDisplayContext()
			? '/wpadmin_pages/insights/scans/results/actions_queue_asset_cards.twig'
			: parent::getRenderTemplate();
	}

	protected function getRenderData() :array {
		if ( $this->isActionsQueueDisplayContext() ) {
			$pane = ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder() )
				->buildActionsQueueThemesPane();

			return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
				'flags'   => [
					'is_disabled' => $pane[ 'is_disabled' ],
				],
				'strings' => [
					'no_issues'         => __( "Previous scans didn't detect any modified, missing, or unrecognised files in theme directories.", 'wp-simple-firewall' ),
					'disabled_message'  => $pane[ 'disabled_message' ],
					'select_asset_hint' => __( 'Select a theme above to review its file table.', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'asset_cards' => $pane[ 'cards' ],
					'count_items' => \count( $pane[ 'cards' ] ),
				],
			] );
		}

		$pane = ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder() )
			->buildRailPaneData( 'themes' );

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_issues' => __( "Previous scans didn't detect any modified, missing, or unrecognised files in theme directories.", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'count_items' => $pane[ 'count_items' ],
			],
			'tab'     => $pane,
			'content' => [],
		] );
	}
}
