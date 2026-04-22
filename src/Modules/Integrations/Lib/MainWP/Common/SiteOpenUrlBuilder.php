<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class SiteOpenUrlBuilder {

	public function build( string $siteID, string $page ) :string {
		return URL::Build( Services::WpGeneral()->getUrl_AdminPage( 'SiteOpen' ), [
			'newWindow'  => 'yes',
			'websiteid'  => $siteID,
			'_opennonce' => wp_create_nonce( 'mainwp-admin-nonce' ),
			'location'   => \base64_encode( \str_replace( Services::WpGeneral()->getAdminUrl(), '', $page ) ),
		] );
	}
}
