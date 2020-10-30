<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\{
	MWPSiteVO,
	SyncVO
};
use MainWP\Dashboard\MainWP_DB;

class LoadShieldSyncData {

	public static function Load( MWPSiteVO $site ) :SyncVO {
		$data = MainWP_DB::instance()->get_website_option(
			$site->getRawDataAsArray(),
			Controller::GetInstance()->prefix( 'mainwp-sync' )
		);
		if ( empty( $data ) ) {
			$data = '[]';
		}
		return ( new SyncVO() )->applyFromArray( json_decode( $data, true ) );
	}
}