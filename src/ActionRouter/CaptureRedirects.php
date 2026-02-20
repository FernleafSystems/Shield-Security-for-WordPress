<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\OperatorModePreference;
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

				$nav = (string)$req->query( Constants::NAV_ID );
				$subNav = (string)$req->query( Constants::NAV_SUB_ID );

				if ( !PluginNavs::NavExists( $nav )
					 || empty( $subNav )
					 || $subNav === PluginNavs::SUBNAV_INDEX
					 || !PluginNavs::NavExists( $nav, $subNav ) ) {
					$mode = ( new OperatorModePreference() )->getCurrent();
					if ( empty( $mode ) ) {
						$redirectTo = $urls->adminHome();
					}
					else {
						$entry = PluginNavs::defaultEntryForMode( $mode );
						$redirectTo = $urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
					}
				}
			}
			elseif ( \preg_match( sprintf( '#^%s-([a-z_]+)$#', \preg_quote( $con->prefix(), '#' ) ), $page, $matches ) ) {
				$nav = PluginNavs::NavExists( $matches[ 1 ] ) ? $matches[ 1 ] : PluginNavs::NAV_DASHBOARD;
				$redirectTo = $urls->adminTopNav( $nav, PluginNavs::GetDefaultSubNavForNav( $nav ) );
			}
			elseif ( $con->comps->opts_lookup->getActivatedPeriod() < 20 && $con->opts->optGet( 'last_wizard_redirect_at' ) === 0 ) {
				$con->opts
					->optSet( 'last_wizard_redirect_at', $req->ts() )
					->store();
				$redirectTo = $urls->adminTopNav( PluginNavs::NAV_WIZARD, PluginNavs::SUBNAV_WIZARD_WELCOME );
			}

			if ( !empty( $redirectTo ) ) {
				Services::Response()->redirect( $redirectTo, [], true, false );
			}
		}
	}
}
