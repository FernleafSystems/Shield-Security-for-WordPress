<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\ImportExport;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Utilities\Data;

class SyncSiteUrlValidatorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => $component === -1
				? ( \parse_url( $url ) ?: false )
				: \parse_url( $url, $component )
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_data'      => new Data(),
			'service_wpgeneral' => new class extends General {
				public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
					return 'https://local.example.com';
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_public_http_url_is_canonicalized() :void {
		$this->assertSame(
			'https://client.example.com/path',
			( new SyncSiteUrlValidator() )->validate( 'https://client.example.com/path/?utm=1' )
		);
	}

	public function test_public_outbound_url_with_public_ip_passes() :void {
		$this->allowWpSafeUrls();

		$this->assertSame(
			'https://client.example.com/path',
			$this->validatorWithResolvedIps( [ '93.184.216.34' ] )
				 ->validatePublicOutbound( 'https://client.example.com/path/?utm=1' )
		);
	}

	public function test_public_outbound_rejects_private_literal_ip() :void {
		$this->allowWpSafeUrls();
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [ '93.184.216.34' ] )->validatePublicOutbound( 'http://127.0.0.1' );
	}

	public function test_public_outbound_rejects_private_resolved_ip() :void {
		$this->allowWpSafeUrls();
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [ '10.0.0.25' ] )->validatePublicOutbound( 'https://client.example.com' );
	}

	public function test_public_outbound_rejects_mixed_public_and_private_resolved_ips() :void {
		$this->allowWpSafeUrls();
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [ '93.184.216.34', '10.0.0.25' ] )
			 ->validatePublicOutbound( 'https://client.example.com' );
	}

	public function test_public_outbound_rejects_unresolved_host() :void {
		$this->allowWpSafeUrls();
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [] )->validatePublicOutbound( 'https://client.example.com' );
	}

	public function test_public_outbound_rejects_wordpress_unsafe_url() :void {
		Functions\when( 'wp_http_validate_url' )->justReturn( false );
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [ '93.184.216.34' ] )
			 ->validatePublicOutbound( 'https://client.example.com' );
	}

	/**
	 * @dataProvider invalidUrlProvider
	 */
	public function test_unsafe_urls_are_rejected( string $url ) :void {
		$this->expectException( \InvalidArgumentException::class );

		( new SyncSiteUrlValidator() )->validate( $url );
	}

	public function invalidUrlProvider() :array {
		return [
			'credentials' => [ 'https://user:pass@client.example.com' ],
			'loopback ip' => [ 'http://127.0.0.1' ],
			'private ip'  => [ 'https://10.0.0.10' ],
			'reserved ip' => [ 'https://192.0.2.20' ],
			'localhost'   => [ 'https://localhost' ],
			'single host' => [ 'https://wordpress-slave' ],
			'local tld'   => [ 'https://client.local' ],
			'self host'   => [ 'https://local.example.com/other-path' ],
			'bad scheme'  => [ 'ftp://client.example.com' ],
			'empty label' => [ 'https://client..example.com' ],
			'bad label'   => [ 'https://client.-example.com' ],
			'underscore'  => [ 'https://client_site.example.com' ],
		];
	}

	private function allowWpSafeUrls() :void {
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $url ) :string => $url );
	}

	private function validatorWithResolvedIps( array $ips ) :SyncSiteUrlValidator {
		return new SyncSiteUrlValidator( static fn() :array => $ips );
	}
}
