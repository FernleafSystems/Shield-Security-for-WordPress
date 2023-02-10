<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\SiteHealth\Analysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SiteHealthController extends ExecOnceModConsumer {

	public const TAB_SLUG = 'shield_security';

	protected function canRun() :bool {
		return Services::WpGeneral()->getWordpressIsAtLeastVersion( '5.8' );
	}

	protected function run() {
		add_action( 'admin_init', [ $this, 'hook' ] );
	}

	public function hook() {
		add_filter( 'site_health_navigation_tabs', function ( $tabs ) {
			$slugs = array_keys( $tabs );
			if ( in_array( '', $slugs, true ) ) {
				/** Position our 'Security' tab immediately after 'Status' tab */
				$anchorPos = array_search( '', $slugs, true ) + 1;
				$tabs = array_slice( $tabs, 0, $anchorPos, true )
						+ [ self::TAB_SLUG => __( 'Security' ) ]
						+ array_slice( $tabs, $anchorPos, count( $tabs ) - $anchorPos, true );
			}
			return $tabs;
		}, 11 );
		add_action( 'site_health_tab_content', function ( $tab ) {
			if ( $tab === self::TAB_SLUG ) {
				$this->renderTab();
			}
		} );
	}

	private function renderTab() {
		echo $this->getCon()->action_router->render( Analysis::SLUG );
	}
}