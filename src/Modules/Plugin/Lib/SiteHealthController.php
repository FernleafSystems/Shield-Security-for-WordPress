<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth\SiteHealthSecurityStatusBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SiteHealthController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const SITE_HEALTH_CAP = 'view_site_health_checks';

	private const SITE_HEALTH_REST_NAMESPACE = 'wp-site-health/v1';

	protected function canRun() :bool {
		$WP = Services::WpGeneral();
		return $WP->getWordpressIsAtLeastVersion( '5.7' )
			   && ( $this->isSiteHealthAdminOrAjaxContext() || $this->isSiteHealthRestRequest() )
			   && apply_filters( 'shield/can_run_site_health_security', self::con()->comps->opts_lookup->isPluginEnabled() );
	}

	protected function run() {
		if ( $this->isSiteHealthAdminOrAjaxContext() ) {
			add_filter( 'site_status_tests', [ $this, 'addSiteStatusTests' ] );
		}
		add_filter( 'user_has_cap', [ $this, 'filterSiteHealthCapability' ], 20, 4 );
	}

	public function addSiteStatusTests( array $tests ) :array {
		$tests[ 'direct' ] = \array_merge(
			$tests[ 'direct' ] ?? [],
			( new SiteHealthSecurityStatusBuilder() )->buildTests()
		);
		return $tests;
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
		return is_admin() || is_network_admin() || $WP->isAjax();
	}

	private function isSiteHealthRestRequest() :bool {
		if ( !Services::Rest()->isRest() ) {
			return false;
		}

		$route = \trim( self::con()->this_req->getRestRoute(), '/' );
		$namespace = self::SITE_HEALTH_REST_NAMESPACE;
		return $route === $namespace || \strpos( $route, $namespace.'/' ) === 0;
	}
}
