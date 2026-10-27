<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest\ShieldCentral;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	CentralOptionsCatalog,
	ConnectionStore
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * ShieldCentral's options/get and options/set routes, called with JSON bodies as ShieldCentral sends them.
 */
class OptionsRoutesIntegrationTest extends ShieldIntegrationTestCase {

	private const CREDENTIAL_KEYS = [
		'admin_access_key',
		'yubikey_app_id',
		'yubikey_api_key',
	];

	private string $siteUuid = '';

	private array $connectionMaterial = [];

	public function set_up() {
		parent::set_up();
		\add_filter( 'shield/central/enabled', '__return_true' );
		$this->siteUuid = \wp_generate_uuid4();
		$this->connectionMaterial = ( new ConnectionStore() )->create( $this->siteUuid )[ 'connection_material' ];
	}

	public function tear_down() {
		\delete_option( ConnectionStore::OPTION_KEY );
		\remove_filter( 'shield/central/enabled', '__return_true' );
		$this->resetRestServer();
		parent::tear_down();
	}

	public function test_routes_need_central_enabled_and_valid_connection_material() :void {
		$xFrame = self::con()->opts->optGet( 'x_frame' );
		$routes = $this->resetRestServer()->get_routes();
		$this->assertArrayHasKey( '/shield/v1/shield-central/options/get', $routes );
		$this->assertArrayHasKey( '/shield/v1/shield-central/options/set', $routes );

		foreach ( [
			'options/get' => [],
			'options/set' => [ 'catalogue_hash' => ( new CentralOptionsCatalog() )->hash(), 'options' => [ 'x_frame' => 'on_deny' ] ],
		] as $route => $body ) {
			$response = $this->dispatch( $route, \array_merge( $body, [
				'site_uuid'           => $this->siteUuid,
				'connection_material' => [ 'shared_secret' => 'wrong' ],
			] ) );
			$this->assertFalse( $response[ 'success' ] );
			$this->assertSame( 'invalid_connection_material', $response[ 'diagnostic_code' ] );
			$this->assertTrue( $response[ 'reconnect_required' ] );
		}
		$this->assertSame( $xFrame, self::con()->opts->optGet( 'x_frame' ) );

		\remove_filter( 'shield/central/enabled', '__return_true' );
		foreach ( [ 'options/get', 'options/set' ] as $route ) {
			$this->assertSame( 404, $this->dispatchRaw( $route, $this->auth() )->get_status() );
		}
	}

	public function test_get_sends_values_access_and_the_catalogue_only_when_its_hash_is_unknown() :void {
		$response = $this->dispatch( 'options/get', $this->auth() );

		$this->assertTrue( $response[ 'success' ] );
		$this->assertSame( self::con()->cfg->version(), $response[ 'version' ] );
		$keys = \array_column( $response[ 'catalogue' ], 'key' );
		$this->assertSame( $keys, \array_keys( $response[ 'values' ] ) );
		$this->assertSame( [], \array_intersect( self::CREDENTIAL_KEYS, $keys ) );
		$this->assertContains( 'xcsp_custom', $response[ 'access' ] );
		$this->assertNotContains( 'x_frame', $response[ 'access' ] );

		$again = $this->dispatch( 'options/get', $this->auth( [ 'known_catalogue_hash' => $response[ 'catalogue_hash' ] ] ) );
		$this->assertSame( $response[ 'catalogue_hash' ], $again[ 'catalogue_hash' ] );
		$this->assertArrayNotHasKey( 'catalogue', $again );
	}

	public function test_every_catalogue_option_has_a_name_and_summary() :void {
		foreach ( ( new CentralOptionsCatalog() )->catalogue() as $option ) {
			$this->assertNotEmpty( $option[ 'name' ], $option[ 'key' ].' has no name' );
			$this->assertNotEmpty( $option[ 'summary' ], $option[ 'key' ].' has no summary' );
		}
	}

	public function test_catalogue_and_hash_are_the_same_whatever_the_licence() :void {
		$free = $this->dispatch( 'options/get', $this->auth() );

		$this->enablePremiumCapabilities( [ 'http_headers_csp', 'whitelabel', 'user_suspension', 'scan_file_locker' ] );
		$pro = $this->dispatch( 'options/get', $this->auth() );

		$this->assertSame( $free[ 'catalogue_hash' ], $pro[ 'catalogue_hash' ] );
		$this->assertSame( $free[ 'catalogue' ], $pro[ 'catalogue' ] );
		$this->assertNotContains( 'xcsp_custom', $pro[ 'access' ] );
	}

	public function test_hash_changes_with_the_version() :void {
		$cfg = self::con()->cfg;
		$properties = $cfg->properties;
		$catalogue = new CentralOptionsCatalog();
		$hash = $catalogue->hash();
		try {
			$cfg->properties = \array_merge( $properties, [ 'version' => $properties[ 'version' ].'1' ] );
			$this->assertNotSame( $hash, $catalogue->hash() );
		}
		finally {
			$cfg->properties = $properties;
		}
	}

	public function test_set_reports_applied_corrected_and_rejected_options() :void {
		$credentials = RuntimeTestState::snapshotOptions( self::CREDENTIAL_KEYS );
		$referrerPolicy = self::con()->opts->optGet( 'x_referrer_policy' );

		$response = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => ( new CentralOptionsCatalog() )->hash(),
			'options'        => [
				'x_frame'           => 'on_deny',
				'antibot_minimum'   => 150,
				'x_referrer_policy' => 'not-a-policy',
				'xcsp_custom'       => [ "default-src 'self'" ],
				'admin_access_key'  => 'new-pin',
				'yubikey_api_key'   => 'new-key',
			],
		] ) );

		$this->assertTrue( $response[ 'success' ] );
		$this->assertSame( [ 'status' => 'applied' ], $response[ 'results' ][ 'x_frame' ] );
		$this->assertSame( [ 'status' => 'corrected' ], $response[ 'results' ][ 'antibot_minimum' ] );
		foreach ( [ 'x_referrer_policy', 'xcsp_custom', 'admin_access_key', 'yubikey_api_key' ] as $rejected ) {
			$this->assertSame( 'rejected', $response[ 'results' ][ $rejected ][ 'status' ], $rejected );
			$this->assertNotEmpty( $response[ 'results' ][ $rejected ][ 'reason' ], $rejected );
		}
		$this->assertSame( 99, $response[ 'values' ][ 'antibot_minimum' ] );
		$this->assertSame( [], \array_intersect( self::CREDENTIAL_KEYS, \array_keys( $response[ 'values' ] ) ) );

		RuntimeTestState::resetOptionsRuntimeCache();
		$opts = self::con()->opts;
		$this->assertSame( 'on_deny', $opts->optGet( 'x_frame' ) );
		$this->assertSame( 99, $opts->optGet( 'antibot_minimum' ) );
		$this->assertSame( $referrerPolicy, $opts->optGet( 'x_referrer_policy' ) );
		$this->assertSame( $credentials, RuntimeTestState::snapshotOptions( self::CREDENTIAL_KEYS ) );
	}

	public function test_clearing_the_csp_rules_empties_the_list_and_turns_off_the_unsent_csp_option() :void {
		$this->enablePremiumCapabilities( [ 'http_headers_csp' ] );
		$hash = ( new CentralOptionsCatalog() )->hash();

		$enabled = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $hash,
			'options'        => [
				'enable_x_content_security_policy' => 'Y',
				'xcsp_custom'                      => [ "default-src 'self';" ],
			],
		] ) );
		$this->assertSame( 'Y', $enabled[ 'values' ][ 'enable_x_content_security_policy' ] );

		$cleared = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $hash,
			'options'        => [ 'xcsp_custom' => [] ],
		] ) );

		$this->assertSame( [ 'xcsp_custom' => [ 'status' => 'applied' ] ], $cleared[ 'results' ] );
		$this->assertSame( [], $cleared[ 'values' ][ 'xcsp_custom' ] );
		$this->assertSame( 'N', $cleared[ 'values' ][ 'enable_x_content_security_policy' ] );
	}

	public function test_set_with_another_catalogue_hash_changes_nothing() :void {
		$xFrame = self::con()->opts->optGet( 'x_frame' );

		$response = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => \hash( 'sha256', 'an older release' ),
			'options'        => [ 'x_frame' => $xFrame === 'on_deny' ? 'off' : 'on_deny' ],
		] ) );

		$this->assertFalse( $response[ 'success' ] );
		$this->assertSame( 'catalogue_hash_mismatch', $response[ 'diagnostic_code' ] );
		$this->assertFalse( $response[ 'reconnect_required' ] );
		$this->assertSame( self::con()->cfg->version(), $response[ 'version' ] );
		$this->assertSame( ( new CentralOptionsCatalog() )->hash(), $response[ 'catalogue_hash' ] );
		$this->assertArrayNotHasKey( 'results', $response );

		RuntimeTestState::resetOptionsRuntimeCache();
		$this->assertSame( $xFrame, self::con()->opts->optGet( 'x_frame' ) );
	}

	private function auth( array $body = [] ) :array {
		return \array_merge( [
			'site_uuid'           => $this->siteUuid,
			'auth_mode'           => 'hmac',
			'connection_material' => $this->connectionMaterial,
		], $body );
	}

	private function dispatch( string $route, array $body ) :array {
		$response = $this->dispatchRaw( $route, $body );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 0, $data[ 'error_code' ] );
		return $data;
	}

	private function dispatchRaw( string $route, array $body ) :\WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/shield/v1/shield-central/'.$route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string)\wp_json_encode( $body ) );
		return $this->resetRestServer()->dispatch( $request );
	}

	private function resetRestServer() :\WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		return \rest_get_server();
	}
}
