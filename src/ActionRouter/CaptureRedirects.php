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

		// Action capture owns these requests, including their nonce and response handling.
		if ( $req->post( ActionData::FIELD_ACTION, $req->query( ActionData::FIELD_ACTION ) ) === ActionData::FIELD_SHIELD ) {
			return;
		}

		if ( is_admin() && !Services::WpGeneral()->isAjax() ) {

			$redirectTo = null;
			// The admin page URL's values: only text is used.
			$query = $req->query;
			$page = ActionData::TransportField( $query, 'page' );

			if ( $page === $urls->rootAdminPageSlug() ) {

				$nav = ActionData::TransportField( $query, Constants::NAV_ID );
				$subNav = ActionData::TransportField( $query, Constants::NAV_SUB_ID );
				$redirectTo = $urls->legacyAdminRouteRedirect( $nav, $subNav, $query );

				if ( empty( $redirectTo )
					 && ( !PluginNavs::NavExists( $nav )
					 || empty( $subNav )
					 || $subNav === PluginNavs::SUBNAV_INDEX
					 || !PluginNavs::NavExists( $nav, $subNav ) ) ) {
					$mode = ( new OperatorModePreference() )->getCurrent();
					if ( empty( $mode ) ) {
						$redirectTo = $urls->adminHome();
					}
					else {
						$redirectTo = $urls->modeHome( $mode );
					}
				}
			}
			elseif ( \preg_match( sprintf( '#^%s-([a-z_]+)$#', \preg_quote( $con->prefix(), '#' ) ), $page, $matches ) ) {
				$nav = $matches[ 1 ];
				$subNav = ActionData::TransportField( $query, Constants::NAV_SUB_ID );
				$redirectTo = $urls->legacyAdminRouteRedirect( $nav, $subNav );
				if ( empty( $redirectTo ) ) {
					$nav = PluginNavs::NavExists( $nav ) ? $nav : PluginNavs::NAV_DASHBOARD;
					$redirectTo = $urls->adminTopNav( $nav,
						PluginNavs::NavExists( $nav, $subNav ) ? $subNav : PluginNavs::GetDefaultSubNavForNav( $nav ) );
				}
			}
			elseif ( $con->comps->opts_lookup->getActivatedPeriod() < 20 && $con->opts->optGet( 'last_wizard_redirect_at' ) === 0 ) {
				$con->opts
					->optSet( 'last_wizard_redirect_at', $req->ts() )
					->store();
				// New installs land on the dashboard, where the setup guide bar walks through the first steps.
				$redirectTo = $urls->adminHome();
			}

			if ( !empty( $redirectTo ) ) {
				Services::Response()->redirect( $redirectTo, [], true, false );
			}
		}
	}
}
