<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CaptureRedirects {

	use PluginControllerConsumer;

	public function run() {
		$con = self::con();
		$urls = $con->plugin_urls;
		$req = Services::Request();

		if ( is_admin() && !Services::WpGeneral()->isAjax() ) {

			$redirectTo = null;
			$page = (string)$req->query( 'page' );

			if ( $page === $urls->rootAdminPageSlug() ) {

				$navID = (string)$req->query( Constants::NAV_ID );
				$subnav = (string)$req->query( Constants::NAV_SUB_ID );

				if ( empty( $navID ) ) {
					$redirectTo = $urls->adminHome();
				}
				elseif ( empty( $subnav ) || $subnav === PluginNavs::SUBNAV_INDEX ) {
					$redirectTo = $urls->adminTopNav( $navID, PluginNavs::GetDefaultSubNavForNav( $navID ) );
				}
			}
			elseif ( \preg_match( sprintf( '#^%s-([a-z_]+)$#', \preg_quote( $con->prefix(), '#' ) ), $page, $matches ) ) {
				$redirectTo = $urls->adminTopNav( $matches[ 1 ], PluginNavs::GetDefaultSubNavForNav( $matches[ 1 ] ) );
			}
			elseif ( $con->getModule_Plugin()->getActivateLength() < 5 ) {
				$redirectTo = $urls->adminTopNav( PluginNavs::NAV_WIZARD, PluginNavs::SUBNAV_WIZARD_WELCOME );
			}

			if ( !empty( $redirectTo ) ) {
				Services::Response()->redirect( $redirectTo, [], true, false );
			}
		}
	}
}