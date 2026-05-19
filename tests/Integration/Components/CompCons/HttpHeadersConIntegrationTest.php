<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HttpHeadersCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class HttpHeadersConIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'x_frame',
			'x_xss_protect',
			'x_content_type',
			'x_referrer_policy',
			'enable_x_content_security_policy',
			'xcsp_custom',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_security_header_options_emit_owned_header_contracts() :void {
		$this->requireController()->opts
			->optSet( 'x_frame', 'on_deny' )
			->optSet( 'x_xss_protect', 'N' )
			->optSet( 'x_content_type', 'Y' )
			->optSet( 'x_referrer_policy', 'no-referrer' )
			->optSet( 'enable_x_content_security_policy', 'N' )
			->optSet( 'xcsp_custom', [] );

		$headers = ( new HttpHeadersCon() )->addToHeaders( [] );

		$this->assertSame( 'DENY', $headers[ 'x-frame-options' ] ?? null );
		$this->assertSame( 'nosniff', $headers[ 'X-Content-Type-Options' ] ?? null );
		$this->assertSame( 'no-referrer', $headers[ 'Referrer-Policy' ] ?? null );
		$this->assertArrayNotHasKey( 'Content-Security-Policy', $headers );
	}

	public function test_disabled_and_empty_header_options_do_not_emit_headers() :void {
		$this->enablePremiumCapabilities( [ 'http_headers_csp' ] );
		$this->requireController()->opts
			->optSet( 'x_frame', 'off' )
			->optSet( 'x_xss_protect', 'N' )
			->optSet( 'x_content_type', 'N' )
			->optSet( 'x_referrer_policy', 'disabled' )
			->optSet( 'enable_x_content_security_policy', 'Y' )
			->optSet( 'xcsp_custom', [] );

		$headers = ( new HttpHeadersCon() )->addToHeaders( [] );

		$this->assertArrayNotHasKey( 'x-frame-options', $headers );
		$this->assertArrayNotHasKey( 'X-Content-Type-Options', $headers );
		$this->assertArrayNotHasKey( 'Referrer-Policy', $headers );
		$this->assertArrayNotHasKey( 'Content-Security-Policy', $headers );
	}

	public function test_existing_headers_are_not_overwritten() :void {
		$this->requireController()->opts
			->optSet( 'x_frame', 'on_deny' )
			->optSet( 'x_xss_protect', 'N' )
			->optSet( 'x_content_type', 'N' )
			->optSet( 'x_referrer_policy', 'disabled' )
			->optSet( 'enable_x_content_security_policy', 'N' );

		$headers = ( new HttpHeadersCon() )->addToHeaders( [
			'X-Frame-Options' => 'SAMEORIGIN',
		] );

		$this->assertSame( 'SAMEORIGIN', $headers[ 'X-Frame-Options' ] ?? null );
		$this->assertArrayNotHasKey( 'x-frame-options', $headers );
	}

	public function test_content_security_policy_requires_option_rules_and_capability() :void {
		$this->enablePremiumCapabilities( [] );
		$this->requireController()->opts
			->optSet( 'x_frame', 'off' )
			->optSet( 'x_xss_protect', 'N' )
			->optSet( 'x_content_type', 'N' )
			->optSet( 'x_referrer_policy', 'disabled' )
			->optSet( 'enable_x_content_security_policy', 'Y' )
			->optSet( 'xcsp_custom', [
				"default-src 'self';",
				"frame-ancestors 'none';",
			] );
		$this->assertArrayNotHasKey( 'Content-Security-Policy', ( new HttpHeadersCon() )->addToHeaders( [] ) );

		$this->enablePremiumCapabilities( [ 'http_headers_csp' ] );
		$this->requireController()->opts
			->optSet( 'enable_x_content_security_policy', 'Y' )
			->optSet( 'xcsp_custom', [
				"default-src 'self';",
				"frame-ancestors 'none';",
			] );
		$headers = ( new HttpHeadersCon() )->addToHeaders( [] );
		$this->assertSame(
			"default-src 'self'; frame-ancestors 'none';",
			$headers[ 'Content-Security-Policy' ] ?? null
		);

		$this->requireController()->opts->optSet( 'xcsp_custom', [ '', '   ' ] );
		$this->assertArrayNotHasKey( 'Content-Security-Policy', ( new HttpHeadersCon() )->addToHeaders( [] ) );
	}
}
