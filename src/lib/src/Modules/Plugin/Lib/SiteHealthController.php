<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\SiteHealth\Analysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SiteHealthController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const TAB_SLUG = 'shield_security';

	protected function canRun() :bool {
		$WP = Services::WpGeneral();
		return $WP->getWordpressIsAtLeastVersion( '5.8' )
			   && !$WP->isAjax()
			   && ( is_admin() || is_network_admin() )
			   && Services::Request()->isGet()
			   && apply_filters( 'shield/can_run_site_health_security', self::con()->comps->opts_lookup->isPluginEnabled() );
	}

	protected function run() {
		add_filter( 'site_health_navigation_tabs', function ( $tabs ) {
			$slugs = \array_keys( $tabs );
			if ( \in_array( '', $slugs, true ) ) {
				/** Position our 'Security' tab immediately after 'Status' tab */
				$anchorPos = \array_search( '', $slugs, true ) + 1;
				$tabs = \array_slice( $tabs, 0, $anchorPos, true )
						+ [ self::TAB_SLUG => __( 'Security' ) ]
						+ \array_slice( $tabs, $anchorPos, \count( $tabs ) - $anchorPos, true );
			}
			return $tabs;
		}, 11 );
		add_action( 'site_health_tab_content', function ( $tab ) {
			if ( $tab === self::TAB_SLUG ) {
				echo self::con()->action_router->render( Analysis::SLUG );
			}
		} );
	}
}