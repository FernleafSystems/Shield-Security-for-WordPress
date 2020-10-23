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

		$sites = apply_filters( 'mainwp_getsites', $mwp->child_file, $mwp->child_key );
		foreach ( $sites as &$site ) {
			$sync = $this->getSiteShieldSyncInfo( $site );
			$site[ 'shield' ] = $sync->getRawDataAsArray();
			$site[ 'shield' ][ 'is_installed' ] = $sync->installed_at ?? false;
			if ( $sync->installed_at > 0 ) {
				$site[ 'shield' ][ 'sync_at_text' ] = $WP->getTimeStringForDisplay( $sync->sync_at );
				$site[ 'shield' ][ 'sync_at_diff' ] = $req->carbon()->setTimestamp( $sync->sync_at )->diffForHumans();
			}
		}
		$data = [
			'vars' => [
				'sites' => $sites,
			]
		];

//		$sites = apply_filters( 'mainwp_getsites', $con->getRootFile(), $this->childKey );
//		var_dump( $sites );
//		?>
		<!--		https://mainwp.com/passing-information-to-your-child-sites/-->
		<!--		<div id="uploader_select_sites_box" class="mainwp_config_box_right">-->
		<!--        --><?php
//		do_action( 'mainwp_select_sites_box', __( "Select Sites", 'mainwp' ), 'checkbox', true, true, 'mainwp_select_sites_box_right', "", [], [] );
//		?><!--</div>-->
		<!--		--><?php
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