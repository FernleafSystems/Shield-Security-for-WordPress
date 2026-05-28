<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth\{
	SiteHealthSecurityStatusBuilder,
	SiteHealthSecurityTabRenderer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SiteHealthController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const SITE_HEALTH_CAP = 'view_site_health_checks';

	private const SITE_HEALTH_REST_NAMESPACE = 'wp-site-health/v1';
	private const FILTER_SHOW_SITE_HEALTH = 'shield/show_site_health';

	protected function canRun() :bool {
		$WP = Services::WpGeneral();
		return $WP->getWordpressIsAtLeastVersion( '5.8' )
			   && ( $this->isSiteHealthAdminOrAjaxContext() || $this->isSiteHealthRestRequest() )
			   && apply_filters( 'shield/can_run_site_health_security', self::con()->comps->opts_lookup->isPluginEnabled() );
	}

	protected function run() {
		$showSiteHealth = $this->isSiteHealthAdminOrAjaxContext() && $this->isSiteHealthDisplayEnabled();

		if ( $showSiteHealth ) {
			add_filter( 'site_status_tests', [ $this, 'addSiteStatusTests' ] );
		}
		if ( $showSiteHealth && $this->isSiteHealthAdminPageContext() ) {
			add_filter( 'site_health_navigation_tabs', [ $this, 'addSiteHealthNavigationTab' ], 11 );
			add_action( 'site_health_tab_content', [ $this, 'renderSiteHealthTab' ] );
		}
		add_filter( 'user_has_cap', [ $this, 'filterSiteHealthCapability' ], 20, 4 );
	}

	public function addSiteStatusTests( array $tests ) :array {
		$tests[ 'direct' ] = \array_merge(
			$tests[ 'direct' ] ?? [],
			( new SiteHealthSecurityStatusBuilder() )->buildTests( $this->siteHealthSecurityTabUrl() )
		);
		return $tests;
	}

	public function addSiteHealthNavigationTab( array $tabs ) :array {
		$slugs = \array_keys( $tabs );
		$tab = [ SiteHealthSecurityStatusBuilder::TAB_SLUG => __( 'Security', 'wp-simple-firewall' ) ];

		if ( \in_array( '', $slugs, true ) ) {
			$anchorPos = \array_search( '', $slugs, true ) + 1;
			return \array_slice( $tabs, 0, $anchorPos, true )
				   + $tab
				   + \array_slice( $tabs, $anchorPos, \count( $tabs ) - $anchorPos, true );
		}

		return $tabs + $tab;
	}

	public function renderSiteHealthTab( string $tab ) :void {
		if ( $tab === SiteHealthSecurityStatusBuilder::TAB_SLUG ) {
			echo ( new SiteHealthSecurityTabRenderer() )->render();
		}
	}

	public function filterSiteHealthCapability( array $allCaps, array $caps, array $args, $user ) :array {
		if ( !$this->isSiteHealthCapabilityCheck( $caps, $args ) || !self::con()->comps->sec_admin->isEnabledSecAdmin() ) {
			return $allCaps;
		}

		$allCaps[ self::SITE_HEALTH_CAP ] = (bool)self::con()->this_req->is_security_admin;
		return $allCaps;
	}

	private function isSiteHealthCapabilityCheck( array $caps, array $args ) :bool {
		$requestedCaps = \array_merge( [ $args[ 0 ] ?? '' ], $caps );
		return \in_array( self::SITE_HEALTH_CAP, \array_map( 'strval', $requestedCaps ), true );
	}

	private function isSiteHealthAdminOrAjaxContext() :bool {
		$WP = Services::WpGeneral();
		return $this->isSiteHealthAdminPageContext() || $WP->isAjax();
	}

	private function isSiteHealthDisplayEnabled() :bool {
		return (bool)apply_filters( self::FILTER_SHOW_SITE_HEALTH, true );
	}

	private function isSiteHealthAdminPageContext() :bool {
		return !Services::WpGeneral()->isAjax() && ( is_admin() || is_network_admin() );
	}

	private function isSiteHealthRestRequest() :bool {
		if ( !Services::Rest()->isRest() ) {
			return false;
		}

		$route = \trim( self::con()->this_req->getRestRoute(), '/' );
		$namespace = self::SITE_HEALTH_REST_NAMESPACE;
		return $route === $namespace || \strpos( $route, $namespace.'/' ) === 0;
	}

	private function siteHealthSecurityTabUrl() :string {
		return admin_url( 'site-health.php?tab='.SiteHealthSecurityStatusBuilder::TAB_SLUG );
	}
}
