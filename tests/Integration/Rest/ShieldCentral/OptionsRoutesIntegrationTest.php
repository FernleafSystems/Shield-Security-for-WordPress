<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest\ShieldCentral;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Profiles\ProfileOptionsCatalog;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\NormaliseConfigComponents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\VerifyPinRequest;
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

	public function test_get_sends_values_unavailable_options_and_the_catalogue_only_when_its_hash_is_unknown() :void {
		$response = $this->dispatch( 'options/get', $this->auth() );

		$this->assertTrue( $response[ 'success' ] );
		$this->assertSame( self::con()->cfg->version(), $response[ 'version' ] );
		$keys = \array_column( $response[ 'catalogue' ], 'key' );
		$this->assertSame( $keys, \array_keys( $response[ 'values' ] ) );
		foreach ( [ 'admin_access_key', 'yubikey_app_id', 'yubikey_api_key' ] as $key ) {
			$this->assertContains( $key, $keys );
		}
		$this->assertContains( 'xcsp_custom', $response[ 'unavailable_options' ] );
		$this->assertNotContains( 'x_frame', $response[ 'unavailable_options' ] );

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
		$this->assertNotContains( 'xcsp_custom', $pro[ 'unavailable_options' ] );
	}

	public function test_an_option_added_by_an_extension_plugin_is_left_out() :void {
		$cfg = self::con()->cfg;
		$original = $cfg->configuration;
		// Added the way ExtensionsCon adds an extension's options.
		$configuration = $cfg->configuration;
		$configuration->options = \array_merge( $configuration->options, ( new NormaliseConfigComponents() )->indexOptions( [
			[
				'key'     => 'extension_test_option',
				'section' => 'section_integrations',
				'type'    => 'text',
				'default' => '',
			],
		] ) );
		$cfg->configuration = $configuration;
		try {
			$this->assertArrayHasKey( 'extension_test_option', ( new ProfileOptionsCatalog() )->profileableOptions() );

			$get = $this->dispatch( 'options/get', $this->auth() );
			$this->assertNotContains( 'extension_test_option', \array_column( $get[ 'catalogue' ], 'key' ) );
			$this->assertArrayNotHasKey( 'extension_test_option', $get[ 'values' ] );
			$this->assertNotContains( 'extension_test_option', $get[ 'unavailable_options' ] );

			$set = $this->dispatch( 'options/set', $this->auth( [
				'catalogue_hash' => $get[ 'catalogue_hash' ],
				'options'        => [ 'extension_test_option' => 'value' ],
			] ) );
			$this->assertSame( 'rejected', $set[ 'results' ][ 'extension_test_option' ][ 'status' ] );
			$this->assertArrayNotHasKey( 'extension_test_option', $set[ 'values' ] );
		}
		finally {
			$cfg->configuration = $original;
		}
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
		$referrerPolicy = self::con()->opts->optGet( 'x_referrer_policy' );

		$response = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => ( new CentralOptionsCatalog() )->hash(),
			'options'        => [
				'x_frame'           => 'on_deny',
				'antibot_minimum'   => 150,
				'x_referrer_policy' => 'not-a-policy',
				'xcsp_custom'       => [ "default-src 'self'" ],
				'yubikey_app_id'    => '12345',
				'yubikey_api_key'   => 'yubico-api-key',
			],
		] ) );

		$this->assertTrue( $response[ 'success' ] );
		foreach ( [ 'x_frame', 'yubikey_app_id', 'yubikey_api_key' ] as $applied ) {
			$this->assertSame( [ 'status' => 'applied' ], $response[ 'results' ][ $applied ], $applied );
		}
		$this->assertSame( [ 'status' => 'corrected' ], $response[ 'results' ][ 'antibot_minimum' ] );
		foreach ( [ 'x_referrer_policy', 'xcsp_custom' ] as $rejected ) {
			$this->assertSame( 'rejected', $response[ 'results' ][ $rejected ][ 'status' ], $rejected );
			$this->assertNotEmpty( $response[ 'results' ][ $rejected ][ 'reason' ], $rejected );
		}
		$this->assertSame( 99, $response[ 'values' ][ 'antibot_minimum' ] );
		$this->assertSame( 'yubico-api-key', $response[ 'values' ][ 'yubikey_api_key' ] );

		RuntimeTestState::resetOptionsRuntimeCache();
		$opts = self::con()->opts;
		$this->assertSame( 'on_deny', $opts->optGet( 'x_frame' ) );
		$this->assertSame( 99, $opts->optGet( 'antibot_minimum' ) );
		$this->assertSame( $referrerPolicy, $opts->optGet( 'x_referrer_policy' ) );
		$this->assertSame( '12345', $opts->optGet( 'yubikey_app_id' ) );
	}

	public function test_the_pin_is_set_from_a_bcrypt_hash_which_shield_then_checks_pins_against() :void {
		$hash = \password_hash( 'a-shared-pin', \PASSWORD_BCRYPT );
		$catalogueHash = ( new CentralOptionsCatalog() )->hash();
		$this->assertFalse( $this->dispatch( 'options/get', $this->auth() )[ 'values' ][ 'admin_access_key' ] );

		$set = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $catalogueHash,
			'options'        => [ 'admin_access_key' => $hash ],
		] ) );
		$get = $this->dispatch( 'options/get', $this->auth( [ 'known_catalogue_hash' => $catalogueHash ] ) );

		$this->assertSame( [ 'admin_access_key' => [ 'status' => 'applied' ] ], $set[ 'results' ] );
		foreach ( [ $set, $get ] as $response ) {
			$this->assertTrue( $response[ 'values' ][ 'admin_access_key' ] );
			$this->assertStringNotContainsString( \substr( $hash, 7 ), (string)\wp_json_encode( $response ) );
		}

		RuntimeTestState::resetOptionsRuntimeCache();
		$this->assertSame( $hash, self::con()->opts->optGet( 'admin_access_key' ) );
		$this->assertTrue( self::con()->comps->sec_admin->isEnabledSecAdmin() );
		$this->assertFalse( ( new VerifyPinRequest() )->run( 'another-pin' ) );
		$this->assertTrue( ( new VerifyPinRequest() )->run( 'a-shared-pin' ) );
	}

	public function test_the_pin_takes_only_a_bcrypt_hash_and_cant_be_emptied() :void {
		$catalogueHash = ( new CentralOptionsCatalog() )->hash();
		$hash = \password_hash( 'a-shared-pin', \PASSWORD_BCRYPT );
		$this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $catalogueHash,
			'options'        => [ 'admin_access_key' => $hash ],
		] ) );

		foreach ( [
			'empty'                 => '',
			'the PIN itself'        => 'a-shared-pin',
			'an md5 hash'           => \hash( 'md5', 'a-shared-pin' ),
			'another bcrypt prefix' => \str_replace( '$2y$', '$2a$', $hash ),
			'a cost crypt refuses'  => \substr_replace( $hash, '03', 4, 2 ),
			'not text'              => [ $hash ],
		] as $case => $value ) {
			$response = $this->dispatch( 'options/set', $this->auth( [
				'catalogue_hash' => $catalogueHash,
				'options'        => [ 'admin_access_key' => $value ],
			] ) );
			$this->assertSame( 'rejected', $response[ 'results' ][ 'admin_access_key' ][ 'status' ], $case );
			$this->assertNotEmpty( $response[ 'results' ][ 'admin_access_key' ][ 'reason' ], $case );
			$this->assertTrue( $response[ 'values' ][ 'admin_access_key' ], $case );
		}

		RuntimeTestState::resetOptionsRuntimeCache();
		$this->assertSame( $hash, self::con()->opts->optGet( 'admin_access_key' ) );
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'malformedListEntryProvider' )]
	public function test_a_list_with_a_malformed_entry_is_rejected_without_stopping_the_save( $entry ) :void {
		$this->enablePremiumCapabilities( [ 'http_headers_csp' ] );
		$hash = ( new CentralOptionsCatalog() )->hash();
		// With rules already saved, Shield's correction of a changed rule list runs, and fails on a non-string entry.
		$this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $hash,
			'options'        => [ 'xcsp_custom' => [ "default-src 'self';" ] ],
		] ) );

		$response = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $hash,
			'options'        => [
				'xcsp_custom' => [ $entry ],
				'x_frame'     => 'on_deny',
			],
		] ) );

		$this->assertTrue( $response[ 'success' ] );
		$this->assertSame( 'rejected', $response[ 'results' ][ 'xcsp_custom' ][ 'status' ] );
		$this->assertNotEmpty( $response[ 'results' ][ 'xcsp_custom' ][ 'reason' ] );
		$this->assertSame( [ "default-src 'self';" ], $response[ 'values' ][ 'xcsp_custom' ] );
		$this->assertSame( [ 'status' => 'applied' ], $response[ 'results' ][ 'x_frame' ] );
		$this->assertSame( 'on_deny', $response[ 'values' ][ 'x_frame' ] );
	}

	public static function malformedListEntryProvider() :array {
		return [
			'nested list' => [ [] ],
			'number'      => [ 5 ],
		];
	}

	public function test_a_value_shield_trims_to_the_one_already_stored_is_reported_corrected() :void {
		$hash = ( new CentralOptionsCatalog() )->hash();
		$this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $hash,
			'options'        => [ 'yubikey_app_id' => '12345' ],
		] ) );

		$response = $this->dispatch( 'options/set', $this->auth( [
			'catalogue_hash' => $hash,
			'options'        => [ 'yubikey_app_id' => ' 12345 ' ],
		] ) );

		$this->assertSame( [ 'yubikey_app_id' => [ 'status' => 'corrected' ] ], $response[ 'results' ] );
		$this->assertSame( '12345', $response[ 'values' ][ 'yubikey_app_id' ] );
	}

	public function test_an_option_wordpress_declines_to_save_is_reported_not_saved() :void {
		$xFrame = self::con()->opts->optGet( 'x_frame' );
		$decline = fn( $value, $oldValue ) => $oldValue;
		$hook = 'pre_update_option_'.self::con()->prefix( 'opts_all', '_' );
		\add_filter( $hook, $decline, 10, 2 );
		try {
			$response = $this->dispatch( 'options/set', $this->auth( [
				'catalogue_hash' => ( new CentralOptionsCatalog() )->hash(),
				'options'        => [ 'x_frame' => $xFrame === 'on_deny' ? 'off' : 'on_deny' ],
			] ) );
		}
		finally {
			\remove_filter( $hook, $decline );
		}

		$this->assertSame( [ 'status' => 'rejected', 'reason' => 'Not saved.' ], $response[ 'results' ][ 'x_frame' ] );
		$this->assertSame( $xFrame, $response[ 'values' ][ 'x_frame' ] );
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
