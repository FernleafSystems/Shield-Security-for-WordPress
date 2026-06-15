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
			'service_data' => new Data(),
		] );
		$this->installHomeUrl( 'https://local.example.com' );
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

	public function test_trusted_sync_allows_hostname_that_resolves_to_private_ip() :void {
		$this->assertSame(
			'https://client.example.com/path',
			$this->validatorWithResolvedIps( [ '10.0.0.25' ] )
				 ->validateTrustedSyncUrl( 'https://client.example.com/path/?utm=1' )
		);
	}

	public function test_trusted_sync_rejects_unresolved_hostname() :void {
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [] )->validateTrustedSyncUrl( 'https://client.example.com' );
	}

	public function test_trusted_sync_allows_same_host_child_path_for_root_home() :void {
		$this->installHomeUrl( 'https://testing.aptotechnologies.com/' );

		$this->assertSame(
			'https://testing.aptotechnologies.com/import4',
			$this->validatorWithResolvedIps( [ '104.21.20.141' ] )
				 ->validateTrustedSyncUrl( 'https://testing.aptotechnologies.com/import4' )
		);
	}

	public function test_strict_validation_rejects_same_host_child_path_for_root_home() :void {
		$this->installHomeUrl( 'https://testing.aptotechnologies.com/' );
		$this->expectException( \InvalidArgumentException::class );

		( new SyncSiteUrlValidator() )->validate( 'https://testing.aptotechnologies.com/import4' );
	}

	/**
	 * @dataProvider trustedSyncUnsafeUrlProvider
	 */
	public function test_trusted_sync_rejects_unsafe_url_inputs( string $url ) :void {
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [ '10.0.0.25' ] )->validateTrustedSyncUrl( $url );
	}

	public function trustedSyncUnsafeUrlProvider() :array {
		return [
			'credentials'  => [ 'https://user:pass@client.example.com' ],
			'private ip'   => [ 'https://10.0.0.10' ],
			'localhost'    => [ 'https://localhost' ],
			'single host'  => [ 'https://wordpress-slave' ],
			'local tld'    => [ 'https://client.local' ],
			'exact self'   => [ 'https://local.example.com' ],
			'bad scheme'   => [ 'ftp://client.example.com' ],
			'malformed'    => [ 'not-a-url' ],
			'bad hostname' => [ 'https://client..example.com' ],
		];
	}

	public function test_same_host_sibling_path_is_allowed_for_subdirectory_home() :void {
		$this->installHomeUrl( 'https://local.example.com/import3' );

		$this->assertSame(
			'https://local.example.com/import4',
			$this->validatorWithResolvedIps( [ '10.0.0.25' ] )
				 ->validateTrustedSyncUrl( 'https://local.example.com/import4' )
		);
	}

	/**
	 * @dataProvider sameHostSelfUrlProvider
	 */
	public function test_same_host_current_site_paths_are_rejected( string $homeUrl, string $targetUrl ) :void {
		$this->installHomeUrl( $homeUrl );
		$this->expectException( \InvalidArgumentException::class );

		$this->validatorWithResolvedIps( [ '10.0.0.25' ] )->validateTrustedSyncUrl( $targetUrl );
	}

	public function sameHostSelfUrlProvider() :array {
		return [
			'root home rejects exact self' => [ 'https://local.example.com', 'https://local.example.com' ],
			'subdirectory home rejects base' => [ 'https://local.example.com/import3', 'https://local.example.com/import3' ],
			'subdirectory home rejects child' => [ 'https://local.example.com/import3', 'https://local.example.com/import3/child' ],
		];
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

	private function installHomeUrl( string $homeUrl ) :void {
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class( $homeUrl ) extends General {
				private string $homeUrl;

				public function __construct( string $homeUrl ) {
					$this->homeUrl = $homeUrl;
				}

				public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
					return $this->homeUrl;
				}
			},
		] );
	}
}
