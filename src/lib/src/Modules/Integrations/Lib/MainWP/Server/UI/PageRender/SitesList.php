<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\PageRender;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\LoadShieldSyncData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\PluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\BaseRender;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

class SitesList extends BaseRender {

	protected function getData() :array {
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
			$mwpSite = ( new MWPSiteVO() )->applyFromArray(
				Services::DataManipulation()->convertStdClassToArray(
					MainWP_DB::instance()->get_website_by_id( $site[ 'id' ] )
				)
			);
			$sync = LoadShieldSyncData::Load( $mwpSite );
			$meta = $sync->meta;

			$shd = $sync->getRawDataAsArray();

			$status = ( new PluginStatus() )
				->setMod( $this->getMod() )
				->setMwpSite( $mwpSite )
				->detect();
			$shd[ 'status_key' ] = key( $status );
			$shd[ 'status' ] = current( $status );

			$shd[ 'is_active' ] = $shd[ 'status_key' ] === PluginStatus::ACTIVE;
			$shd[ 'is_inactive' ] = $shd[ 'status_key' ] === PluginStatus::INACTIVE;
			$shd[ 'is_notpro' ] = $shd[ 'status_key' ] === PluginStatus::NOT_PRO;
			$shd[ 'is_mwpnoton' ] = $shd[ 'status_key' ] === PluginStatus::MWP_NOT_ON;
			$shd[ 'is_sync_rqd' ] = $shd[ 'status_key' ] === PluginStatus::NEED_SYNC;
			$shd[ 'is_version_mismatch' ] = in_array( $shd[ 'status_key' ], [
				PluginStatus::VERSION_NEWER_THAN_SERVER,
				PluginStatus::VERSION_OLDER_THAN_SERVER,
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

				$shd[ 'issues_href' ] = add_query_arg(
					[
						'newWindow' => 'yes',
						'websiteid' => $site[ 'id' ],
						'location'  => base64_encode( $this->getScanPageUrlPart() )
					],
					Services::WpGeneral()->getUrl_AdminPage( 'SiteOpen' )
				);
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
				'st_notpro'           => __( "ShieldPRO isn't activated on this site.", 'wp-simple-firewall' ),
				'st_mwpnoton'         => __( "Shield's MainWP option isn't switch on for this site.", 'wp-simple-firewall' ),
				'st_sync_rqd'         => __( 'Shield Security plugin needs to sync.', 'wp-simple-firewall' ),
				'st_version_mismatch' => __( 'Shield Security plugin versions are out of sync.', 'wp-simple-firewall' ),
				'st_not_detected'     => __( 'Shield Security plugin not detected in last sync.', 'wp-simple-firewall' ),
			]
		];
	}

	private function getScanPageUrlPart() :string {
		$WP = Services::WpGeneral();
		return str_replace(
			$WP->getAdminUrl(),
			'',
			$this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' )
		);
	}

	protected function getTemplateSlug() :string {
		return 'pages/sites';
	}
}