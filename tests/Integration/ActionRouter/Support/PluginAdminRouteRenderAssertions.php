<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;

trait PluginAdminRouteRenderAssertions {

	private function assertRouteRenderOutputHealthy( array $payload, string $label ) :string {
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ), $label.' action success contract' );
		$this->assertFalse( (bool)( $payload[ 'render_error' ] ?? true ), $label.' render error flag contract' );
		$this->assertSame( '', (string)( $payload[ 'render_error_code' ] ?? '' ), $label.' render error code contract' );
		$this->assertArrayHasKey( 'render_template', $payload, $label.' render template contract' );
		$this->assertIsString( $payload[ 'render_template' ], $label.' render template contract' );
		return (string)( $payload[ 'render_output' ] ?? '' );
	}

	private function processActionPayloadWithAdminBypass( string $actionSlug, array $params = [] ) :array {
		return ( new PluginAdminRouteRuntime() )
			->processActionPayloadWithAdminBypass( $actionSlug, $params );
	}

	private function renderPluginAdminRoutePayload( string $nav, string $subNav, array $extra = [] ) :array {
		return ( new PluginAdminRouteRuntime() )
			->renderPluginAdminRoutePayload( $nav, $subNav, $extra );
	}

	private function assertPluginAdminShellRouteState( array $payload, string $subNav ) :void {
		$vars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$this->assertSame( $subNav, (string)( $vars[ 'active_module_settings' ] ?? '' ) );
		$this->assertArrayHasKey( 'nav_sidebar', $vars );
	}
}
