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

				if ( empty( $navID ) ) {
					$redirectTo = $urls->adminHome();
				}
				elseif ( empty( $req->query( Constants::NAV_SUB_ID ) ) ) {
					$redirectTo = $this->getRedirectForNavID( $navID );
				}
			}
			elseif ( \preg_match( sprintf( '#^%s-([a-z_]+)$#', \preg_quote( $con->prefix(), '#' ) ), $page, $matches ) ) {
				$redirectTo = $this->getRedirectForNavID( $matches[ 1 ] );
			}
			elseif ( $con->getModule_Plugin()->getActivateLength() < 5 ) {
				$redirectTo = $urls->adminTopNav( PluginNavs::NAV_WIZARD, PluginNavs::SUBNAV_WIZARD_WELCOME );
			}

			if ( !empty( $redirectTo ) ) {
				Services::Response()->redirect( $redirectTo, [], true, false );
			}
		}
	}

	private function getRedirectForNavID( string $navID ) :?string {
		$con = self::con();
		$urls = $con->plugin_urls;

		switch ( $navID ) {
			case PluginNavs::NAV_DASHBOARD:
				$redirect = $urls->adminHome();
				break;
			case PluginNavs::NAV_OPTIONS_CONFIG:
				$redirect = $urls->modCfg( $con->getModule_Plugin() );
				break;
			case PluginNavs::NAV_ACTIVITY:
				$redirect = $urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_LOG );
				break;
			case PluginNavs::NAV_IPS:
				$redirect = $urls->adminIpRules();
				break;
			case PluginNavs::NAV_REPORTS:
				$redirect = $urls->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST );
				break;
			case PluginNavs::NAV_SCANS:
				$redirect = $urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS );
				break;
			case PluginNavs::NAV_TRAFFIC:
				$redirect = $urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_TRAFFIC_LOG );
				break;
			default:
				$redirect = null;
				break;
		}
		return $redirect;
	}
}