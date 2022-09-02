<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\TabRender;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\LoadShieldSyncData;
use FernleafSystems\Wordpress\Services\Services;

class SitesList extends BaseTab {

	const TAB_SLUG = 'sites_list';

	protected function getPageSpecificData() :array {
		$mod = $this->getMod();
		$mwp = $this->getCon()->mwpVO;
		$WP = Services::WpGeneral();
		$req = Services::Request();

		$statsHead = [
			'connected'    => 0,
			'disconnected' => 0,
			'with_issues'  => 0,
			'needs_update' => 0,
		];
		$sites = apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
		foreach ( $sites as &$site ) {
			$mwpSite = MWPSiteVO::LoadByID( (int)$site[ 'id' ] );
			$sync = LoadShieldSyncData::Load( $mwpSite );
			$meta = $sync->meta;

			$shd = $sync->getRawData();
			$status = ( new ClientPluginStatus() )
				->setMod( $this->getMod() )
				->setMwpSite( $mwpSite )
				->detect();
			$shd[ 'status_key' ] = key( $status );
			$shd[ 'status' ] = current( $status );

			$shd[ 'is_active' ] = $shd[ 'status_key' ] === ClientPluginStatus::ACTIVE;
			$shd[ 'is_inactive' ] = $shd[ 'status_key' ] === ClientPluginStatus::INACTIVE;
			$shd[ 'is_notinstalled' ] = $shd[ 'status_key' ] === ClientPluginStatus::NOT_INSTALLED;
			$shd[ 'is_notpro' ] = $shd[ 'status_key' ] === ClientPluginStatus::NOT_PRO;
			$shd[ 'is_mwpnoton' ] = $shd[ 'status_key' ] === ClientPluginStatus::MWP_NOT_ON;
			$shd[ 'is_sync_rqd' ] = $shd[ 'status_key' ] === ClientPluginStatus::NEED_SYNC;
			$shd[ 'is_version_mismatch' ] = in_array( $shd[ 'status_key' ], [
				ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
				ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
			] );
			$shd[ 'can_sync' ] = in_array( $shd[ 'status_key' ], [
				ClientPluginStatus::ACTIVE,
				ClientPluginStatus::NEED_SYNC,
			] );

			if ( $shd[ 'is_active' ] ) {

				$statsHead[ 'connected' ]++;
				$shd[ 'sync_at_text' ] = $WP->getTimeStringForDisplay( $meta->sync_at );
				$shd[ 'sync_at_diff' ] = $req->carbon()->setTimestamp( $meta->sync_at )->diffForHumans();

				if ( empty( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] ) ) {
					$shd[ 'issues' ] = __( 'No Issues', 'wp-simple-firewall' );
					$shd[ 'has_issues' ] = false;
				}
				else {
					$shd[ 'has_issues' ] = true;
					$shd[ 'issues' ] = array_sum( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] );
					$statsHead[ 'with_issues' ]++;
				}

				$shd[ 'href_issues' ] = add_query_arg(
					[
						'newWindow' => 'yes',
						'websiteid' => $site[ 'id' ],
						'location'  => base64_encode( $this->getScanPageUrlPart() )
					],
					Services::WpGeneral()->getUrl_AdminPage( 'SiteOpen' )
				);
				$shd[ 'href_manage' ] = $this->createInternalExtensionHref( [
					'tab'     => 'site_manage',
					'site_id' => $site[ 'id' ],
				] );
			}
			else {
				$statsHead[ 'disconnected' ]++;
			}

			$statsHead[ 'needs_update' ] += $meta->has_update ? 1 : 0;

			$site[ 'shield' ] = $shd;
		}

		return [
			'vars'    => [
				'sites'      => $sites,
				'stats_head' => $statsHead,
			],
			'strings' => [
				'manage'              => __( 'Manage', 'wp-simple-firewall' ),
				'actions'             => __( 'Actions', 'wp-simple-firewall' ),
				'site'                => __( 'Site', 'wp-simple-firewall' ),
				'url'                 => __( 'URL', 'wp-simple-firewall' ),
				'issues'              => __( 'Issues', 'wp-simple-firewall' ),
				'status'              => __( 'Status', 'wp-simple-firewall' ),
				'last_sync'           => __( 'Last Sync', 'wp-simple-firewall' ),
				'last_scan'           => __( 'Last Scan', 'wp-simple-firewall' ),
				'version'             => __( 'Version', 'wp-simple-firewall' ),
				'connected'           => __( 'Connected', 'wp-simple-firewall' ),
				'disconnected'        => __( 'Disconnected', 'wp-simple-firewall' ),
				'with_issues'         => __( 'With Issues', 'wp-simple-firewall' ),
				'needs_update'        => __( 'Needs Update', 'wp-simple-firewall' ),
				'st_inactive'         => __( 'Shield Security plugin is installed but not activated.', 'wp-simple-firewall' ),
				'st_notinstalled'     => __( "Shield Security plugin not detected in last sync.", 'wp-simple-firewall' ),
				'st_notpro'           => __( "ShieldPRO isn't activated on this site.", 'wp-simple-firewall' ),
				'st_mwpnoton'         => __( "Shield's MainWP integration isn't enabled for this site.", 'wp-simple-firewall' ),
				'st_sync_rqd'         => __( 'Shield Security plugin needs to sync.', 'wp-simple-firewall' ),
				'st_version_mismatch' => __( 'Shield Security plugin versions are out of sync.', 'wp-simple-firewall' ),
				'st_unknown'          => __( "Couldn't determine Shield plugin status.", 'wp-simple-firewall' ),
				'act_sync'            => __( 'Sync Shield', 'wp-simple-firewall' ),
				'act_activate'        => __( 'Activate Shield', 'wp-simple-firewall' ),
				'act_align'           => __( 'Align Shield', 'wp-simple-firewall' ),
				'act_deactivate'      => __( 'Deactivate Shield', 'wp-simple-firewall' ),
				'act_install'         => __( 'Install Shield', 'wp-simple-firewall' ),
				'act_upgrade'         => __( 'Upgrade Shield', 'wp-simple-firewall' ),
				'act_uninstall'       => __( 'Uninstall Shield', 'wp-simple-firewall' ),
				'act_license'         => __( 'Check ShieldPRO License', 'wp-simple-firewall' ),
				'act_mwp'             => __( 'Switch-On MainWP Integration', 'wp-simple-firewall' ),
			]
		];
	}

	private function getScanPageUrlPart() :string {
		$WP = Services::WpGeneral();
		return str_replace(
			$WP->getAdminUrl(),
			'',
			$this->getCon()->getModule_Insights()->getUrl_ScansResults()
		);
	}

	protected function getTemplateSlug() :string {
		return 'pages/sites.twig';
	}
}