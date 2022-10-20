<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\LoadShieldSyncData;
use FernleafSystems\Wordpress\Services\Services;

class SitesListTableColumn extends BaseRender {

	use Traits\SecurityAdminNotRequired;

	const SLUG = 'mainwp_sites_list_table_column';

	/**
	 * @inheritDoc
	 */
	protected function getRenderData() :array {
		$con = $this->getCon();
		$workingSite = ( new MWPSiteVO() )->applyFromArray( $this->action_data[ 'raw_mainwp_site_data' ] );

		$sync = LoadShieldSyncData::Load( $workingSite );
		$status = ( new ClientPluginStatus() )
			->setMod( $this->getMod() )
			->setMwpSite( $workingSite )
			->detect();

		$statusKey = key( $status );
		$isActive = $statusKey === ClientPluginStatus::ACTIVE;
		if ( $isActive ) {
			$issuesCount = array_sum( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] );
		}
		else {
			$issuesCount = 0;
		}

		return [
			'flags'   => [
				'is_active'           => $isActive,
				'is_sync_rqd'         => $statusKey === ClientPluginStatus::NEED_SYNC,
				'is_inactive'         => $statusKey === ClientPluginStatus::INACTIVE,
				'is_notpro'           => $statusKey === ClientPluginStatus::NOT_PRO,
				'is_mwpnoton'         => $statusKey === ClientPluginStatus::MWP_NOT_ON,
				'is_version_mismatch' => in_array( $statusKey, [
					ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
					ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
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
				'tooltip_notpro'           => __( "The Shield plugin on this site doesn't have an active ShieldPRO license.", 'wp-simple-firewall' ),
				'tooltip_mwpnoton'         => __( "Shield's MainWP integration isn't enabled for this site.", 'wp-simple-firewall' ),
				'tooltip_not_installed'    => __( "Shield isn't installed on this site.", 'wp-simple-firewall' ),
				'tooltip_sync_required'    => __( "Sync Required.", 'wp-simple-firewall' ),
				'tooltip_version_mismatch' => __( "Shield version on site doesn't match this server.", 'wp-simple-firewall' ),
				'tooltip_please_update'    => __( "Please update your Shield plugins to the same versions and re-sync.", 'wp-simple-firewall' ),
				'tooltip_issues_found'     => __( "Issues Found", 'wp-simple-firewall' ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'raw_mainwp_site_data'
		];
	}
}