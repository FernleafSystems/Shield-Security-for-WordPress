<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;

trait PluginAdminRouteRenderAssertions {

	private function assertRouteRenderOutputHealthy( array $payload, string $label ) :string {
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertNotSame( '', $html, 'Expected non-empty render output for '.$label.'.' );
		$this->assertHtmlNotContainsMarker( 'Exception during render', $html, $label.' render exception check' );
		return $html;
	}

	private function processActionPayloadWithAdminBypass( string $actionSlug, array $params = [] ) :array {
		return ( new PluginAdminRouteRuntime() )
			->processActionPayloadWithAdminBypass( $actionSlug, $params );
	}

	private function renderPluginAdminRoutePayload( string $nav, string $subNav, array $extra = [] ) :array {
		return ( new PluginAdminRouteRuntime() )
			->renderPluginAdminRoutePayload( $nav, $subNav, $extra );
	}
}
