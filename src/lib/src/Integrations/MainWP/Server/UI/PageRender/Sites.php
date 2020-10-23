<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\PageRender;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

class Sites extends BaseRender {

	protected function getData() :array {
		$con = $this->getCon();
		$mwp = $con->mwpVO;
		$WP = Services::WpGeneral();
		$req = Services::Request();

		$statsHead = [
			'active'       => 0,
			'inactive'     => 0,
			'with_issues'  => 0,
			'needs_update' => 0,
		];
		$sites = apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
		foreach ( $sites as &$site ) {
			$sync = $this->getSiteShieldSyncInfo( $site );
			$site[ 'shield' ] = $sync->getRawDataAsArray();
			$site[ 'shield' ][ 'is_installed' ] = $sync->installed_at ?? false;
			if ( $sync->installed_at > 0 ) {
				$statsHead[ 'active' ]++;
				$site[ 'shield' ][ 'sync_at_text' ] = $WP->getTimeStringForDisplay( $sync->sync_at );
				$site[ 'shield' ][ 'sync_at_diff' ] = $req->carbon()->setTimestamp( $sync->sync_at )->diffForHumans();

				$statsHead[ 'with_issues' ] += $sync->has_update ? 1 : 0;
				$statsHead[ 'needs_update' ] += $sync->has_update ? 1 : 0;
			}
			else {
				$statsHead[ 'inactive' ]++;
			}
		}

		$data = [
			'vars'    => [
				'sites'      => $sites,
				'stats_head' => $statsHead,
			],
			'strings' => [
				'site'         => __( 'Site', 'wp-simple-firewall' ),
				'url'          => __( 'URL', 'wp-simple-firewall' ),
				'status'       => __( 'Status', 'wp-simple-firewall' ),
				'last_sync'    => __( 'Last Sync', 'wp-simple-firewall' ),
				'last_scan'    => __( 'Last Scan', 'wp-simple-firewall' ),
				'version'      => __( 'Version', 'wp-simple-firewall' ),
				'active'       => __( 'Active', 'wp-simple-firewall' ),
				'inactive'     => __( 'Inactive', 'wp-simple-firewall' ),
				'with_issues'  => __( 'With Issues', 'wp-simple-firewall' ),
				'needs_update' => __( 'Needs Update', 'wp-simple-firewall' ),
				'not_detected' => __( 'Shield Security plugin not detected in last sync.', 'wp-simple-firewall' ),
			]
		];

		return $data;
	}

	protected function getSiteShieldSyncInfo( $site ) :SyncVO {
		$data = MainWP_DB::instance()->get_website_option(
			$site,
			$this->getCon()->prefix( 'mainwp-sync' )
		);
		if ( empty( $data ) ) {
			$data = '[]';
		}
		return ( new SyncVO() )->applyFromArray( json_decode( $data, true ) );
	}

	protected function getTemplateSlug() :string {
		return 'sites';
	}
}