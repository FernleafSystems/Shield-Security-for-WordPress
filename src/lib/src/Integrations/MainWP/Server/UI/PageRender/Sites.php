<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\PageRender;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\SyncVO;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data\DetermineClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\BaseRender;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

class Sites extends BaseRender {

	protected function getData() :array {
		$mwp = $this->getCon()->mwpVO;
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
			$meta = $sync->meta;

			$shd = $sync->getRawDataAsArray();
			$shd[ 'is_installed' ] = $meta->installed_at ?? false;
			if ( $meta->installed_at > 0 ) {
				$statsHead[ 'active' ]++;
				$shd[ 'sync_at_text' ] = $WP->getTimeStringForDisplay( $meta->sync_at );
				$shd[ 'sync_at_diff' ] = $req->carbon()->setTimestamp( $meta->sync_at )->diffForHumans();

				if ( empty( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] ) ) {
					$shd[ 'issues' ] = __( 'No Issues', 'wp-simple-firewall' );
					$shd[ 'has_issues' ] = false;
				}
				else {
					$shd[ 'has_issues' ] = true;
					$shd[ 'issues' ] = sprintf( '%s: %s', __( 'Issues', 'wp-simple-firewall' ), array_sum( $sync->modules[ 'hack_protect' ][ 'scan_issues' ] ) );
					$statsHead[ 'with_issues' ]++;
				}

				$status = ( new DetermineClientPluginStatus() )
					->setCon( $this->getCon() )
					->run( $sync );
				var_dump($status);
				$shd[ 'status_key' ] = reset( $status );
				$shd[ 'status' ] = current( $status );

				$statsHead[ 'needs_update' ] += $meta->has_update ? 1 : 0;
			}
			else {
				$statsHead[ 'inactive' ]++;
			}

			$site[ 'shield' ] = $shd;
		}

		return [
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
		return 'pages/sites';
	}
}