<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateByCore extends BaseInvestigateAsset {

	public const SLUG = 'plugin_admin_page_investigate_by_core';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_core.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$coreVersion = $this->getCoreVersion();
		$coreFileCount = $this->getCoreFileIssueCount();
		$activityCount = $this->countActivityForSubject(
			InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::SUBJECT_TYPE_CORE
		);
		$hasCoreUpdate = $this->hasCoreUpdate();
		$strings = [
			'inner_page_title'       => __( 'Investigate WordPress Core', 'wp-simple-firewall' ),
			'inner_page_subtitle'    => __( 'Review WordPress core integrity and relevant platform activity.', 'wp-simple-firewall' ),
			'overview_title'         => __( 'WordPress Core Overview', 'wp-simple-firewall' ),
			'back_to_investigate'    => __( 'Back To Investigate', 'wp-simple-firewall' ),
			'file_status_empty_text' => __( 'No file status records were found for this subject.', 'wp-simple-firewall' ),
			'activity_empty_text'    => __( 'No activity records were found for this subject.', 'wp-simple-firewall' ),
		];

		$tabs = $this->buildAssetTabsPayload( InvestigationTableContract::SUBJECT_TYPE_CORE, [
			'file_status' => $coreFileCount,
			'activity'    => $activityCount,
		], false );
		$tables = $this->buildAssetTables(
			InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::SUBJECT_TYPE_CORE,
			'core_'
		);
		$tables[ 'file_status' ] = $this->withEmptyStateTableContract(
			$tables[ 'file_status' ],
			$coreFileCount,
			$strings[ 'file_status_empty_text' ]
		);
		$tables[ 'activity' ] = $this->withEmptyStateTableContract(
			$tables[ 'activity' ],
			$activityCount,
			$strings[ 'activity_empty_text' ]
		);

		return [
			'flags'   => [
				'has_subject' => true,
			],
			'hrefs'   => [
				'back_to_investigate' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ),
				'by_core'             => $con->plugin_urls->investigateByCore(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'wordpress' ),
			],
			'strings' => $strings,
			'vars'    => [
				'subject'        => [
					'status'       => $this->mapCountToStatus( $hasCoreUpdate ? 1 : 0, 'good', 'warning' ),
					'title'        => __( 'WordPress Core', 'wp-simple-firewall' ),
					'avatar_icon'  => $con->svgs->iconClass( 'wordpress' ),
					'meta'         => [
						[
							'label' => __( 'Version', 'wp-simple-firewall' ),
							'value' => $coreVersion,
						],
						[
							'label' => __( 'Install Directory', 'wp-simple-firewall' ),
							'value' => wp_normalize_path( ABSPATH ),
						],
					],
					'status_pills' => [
						[
							'status' => $this->mapCountToStatus( $hasCoreUpdate ? 1 : 0, 'good', 'warning' ),
							'label'  => $hasCoreUpdate
								? __( 'Core Update Available', 'wp-simple-firewall' )
								: __( 'Core Up To Date', 'wp-simple-firewall' ),
						],
					],
				],
				'summary'        => [
					'file_status' => [
						'label'  => __( 'File Issues', 'wp-simple-firewall' ),
						'count'  => $coreFileCount,
						'status' => $this->mapCountToStatus( $coreFileCount, 'good', 'warning' ),
					],
					'activity'    => [
						'label'  => __( 'Activity', 'wp-simple-firewall' ),
						'count'  => $activityCount,
						'status' => $this->mapCountToStatus( $activityCount, 'info', 'warning' ),
					],
				],
				'tabs'           => $tabs,
				'rail_nav_items' => $this->buildRailNavItemsFromTabs( $tabs ),
				'tables'         => $tables,
				'overview_rows'  => [
					[
						'label' => __( 'WordPress Version', 'wp-simple-firewall' ),
						'value' => $coreVersion,
					],
					[
						'label' => __( 'Core Update', 'wp-simple-firewall' ),
						'value' => $hasCoreUpdate
							? __( 'An update is available.', 'wp-simple-firewall' )
							: __( 'No update available.', 'wp-simple-firewall' ),
					],
					[
						'label' => __( 'Install Directory', 'wp-simple-firewall' ),
						'value' => wp_normalize_path( ABSPATH ),
					],
				],
			],
		];
	}

	protected function getCoreVersion() :string {
		return Services::WpGeneral()->getVersion();
	}

	protected function hasCoreUpdate() :bool {
		return Services::WpGeneral()->hasCoreUpdate();
	}

	protected function getCoreFileIssueCount() :int {
		return ( new Counts( RetrieveCount::CONTEXT_RESULTS_DISPLAY ) )->countWPFiles();
	}
}
