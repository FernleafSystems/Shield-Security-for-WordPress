<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\ManageSites;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data\PluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

class SitesListTableHandler extends BaseRender {

	use PluginControllerConsumer;
	use OneTimeExecute;

	/**
	 * @var MWPSiteVO
	 */
	private $workingItem;

	protected function run() {
		add_filter( 'mainwp_sitestable_getcolumns', function ( $columns ) {
			$columns[ 'shield' ] = 'Shield';
			return $columns;
		}, 10, 1 );
		add_filter( 'mainwp_sitestable_item', function ( array $item ) {
			$item[ 'shield' ] = $this->renderShieldColumnEntryForItem( $item );
			return $item;
		}, 10, 1 );
	}

	private function renderShieldColumnEntryForItem( array $item ) :string {
		$this->workingItem = ( new MWPSiteVO() )->applyFromArray( $item );
		return $this->render();
	}

	protected function getData() :array {
		$con = $this->getCon();
		$syncData = MainWP_DB::instance()->get_website_option(
			$this->workingItem->getRawDataAsArray(),
			$con->prefix( 'mainwp-sync' )
		);

		$sync = ( new SyncVO() )->applyFromArray( empty( $syncData ) ? [] : json_decode( $syncData, true ) );
		$status = ( new PluginStatus() )
			->setCon( $this->getCon() )
			->setMwpSite( $this->workingItem )
			->detect();

		$statusKey = key( $status );
		$isActive = $statusKey === PluginStatus::ACTIVE;
		if ( $isActive ) {
			$issuesCount = array_sum( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] );
		}
		else {
			$issuesCount = 0;
		}

		return [
			'flags'   => [
				'is_active'           => $isActive,
				'is_sync_rqd'         => $statusKey === PluginStatus::NEED_SYNC,
				'is_inactive'         => $statusKey === PluginStatus::INACTIVE,
				'is_version_mismatch' => in_array( $statusKey, [
					PluginStatus::VERSION_NEWER_THAN_SERVER,
					PluginStatus::VERSION_OLDER_THAN_SERVER,
				] ),
			],
			'vars'    => [
				'status_key'   => $statusKey,
				'status_name'  => current( $status ),
				'issues_count' => $issuesCount,
				'version'      => $this->getCon()->getVersion()
			],
			'hrefs'   => [
				'this_extension' => Services::WpGeneral()
											->getUrl_AdminPage( $con->mwpVO->official_extension_data[ 'page' ] ),
			],
			'strings' => [
				'tooltip_inactive'         => __( "Shield plugin is installed, but not active.", 'wp-simple-firewall' ),
				'tooltip_not_installed'    => __( "Shield isn't installed on this site.", 'wp-simple-firewall' ),
				'tooltip_sync_required'    => __( "Sync Required.", 'wp-simple-firewall' ),
				'tooltip_version_mismatch' => __( "Shield version on site doesn't match this server.", 'wp-simple-firewall' ),
				'tooltip_please_update'    => __( "Please update your Shield plugins to the same versions and re-sync.", 'wp-simple-firewall' ),
				'tooltip_issues_found'     => __( "Issues Found", 'wp-simple-firewall' ),
			]
		];
	}

	protected function getTemplateSlug() :string {
		return 'tables/manage_sites_col';
	}
}