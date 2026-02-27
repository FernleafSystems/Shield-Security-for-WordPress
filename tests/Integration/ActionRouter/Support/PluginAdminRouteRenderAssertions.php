<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Constants
};

trait PluginAdminRouteRenderAssertions {

	private function processActionPayloadWithAdminBypass( string $actionSlug, array $params = [] ) :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			return ( new ActionProcessor() )
						->processAction( $actionSlug, $params )
						->payload();
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
	}

	private function renderPluginAdminRoutePayload( string $nav, string $subNav, array $extra = [] ) :array {
		return $this->processActionPayloadWithAdminBypass(
			PageAdminPlugin::SLUG,
			\array_merge(
				[
					Constants::NAV_ID     => $nav,
					Constants::NAV_SUB_ID => $subNav,
				],
				$extra
			)
		);
	}
}

