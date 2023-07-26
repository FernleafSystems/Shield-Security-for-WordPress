<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\{
	MWPSiteVO,
	SyncVO
};
use MainWP\Dashboard\MainWP_DB;

class LoadShieldSyncData {

	public static function Load( MWPSiteVO $site ) :SyncVO {
		$data = MainWP_DB::instance()->get_website_option(
			$site->getRawData(),
			Controller::GetInstance()->prefix( 'mainwp-sync' )
		);
		$decoded = empty( $data ) ? [] : \json_decode( $data, true );
		return ( new SyncVO() )->applyFromArray( \is_array( $decoded ) ? $decoded : [] );
	}
}