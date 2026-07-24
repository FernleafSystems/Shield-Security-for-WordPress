<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa\LoginIntent\LoginIntentFormFieldBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\BaseLoginIntentPage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components\{
	BaseForm,
	LoginIntentFormShield
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
};

class MfaLoginIntentRenderContractsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'DAY_IN_SECONDS' ) ) {
			\define( 'DAY_IN_SECONDS', 86400 );
		}

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'esc_attr' )->alias( static fn( $value ) => $value );
		Functions\when( 'esc_url_raw' )->alias( static fn( $value ) => $value );
		Functions\when( 'wp_hash' )->alias( static fn( string $value ) :string => \hash( 'sha256', $value ) );
		Functions\when( 'wp_create_nonce' )->justReturn( 'rest-nonce' );
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blogID, string $path ) :string => 'https://example.com/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'add_query_arg' )->alias(
			static fn( array $data, string $url ) :string => $url.'?'.\http_build_query( $data )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static fn( $value ) => \is_array( $value ) ? \array_map( '\rawurlencode', $value ) : \rawurlencode( (string)$value )
		);
		Functions\when( 'wp_validate_redirect' )->alias( static fn( string $url, string $fallback ) :string => $url === '' ? $fallback : $url );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => $component === -1 ? \parse_url( $url ) : \parse_url( $url, $component )
		);

		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_login_field_base_normalizes_optional_render_contract_values() :void {
		$action = new LoginIntentFormFieldBaseTestDouble( [
			'vars' => [
				'provider_slug' => 'email',
				'field'         => [
					'slug'              => 77,
					'name'              => 88,
					'type'              => 99,
					'text'              => 123,
					'hidden_input_name' => 456,
					'classes'           => [ 'alpha', 99, '' ],
					'datas'             => [
						'auto_send' => 1,
						''          => 'discard-me',
					],
					'supp'              => [
						'send_email' => 321,
					],
				],
			],
		] );

		$action->checkAvailableDataForTest();
		$field = $action->renderDataForTest()[ 'field' ];

		$this->assertSame( '77', $field[ 'slug' ] );
		$this->assertSame( '88', $field[ 'name' ] );
		$this->assertSame( '99', $field[ 'type' ] );
		$this->assertSame( '123', $field[ 'text' ] );
		$this->assertSame( '456', $field[ 'hidden_input_name' ] );
		$this->assertSame( 'input', $field[ 'element' ] );
		$this->assertSame( '88', $field[ 'id' ] );
		$this->assertSame( '', $field[ 'value' ] );
		$this->assertSame( '', $field[ 'placeholder' ] );
		$this->assertSame( '', $field[ 'description' ] );
		$this->assertSame( '', $field[ 'help_link' ] );
		$this->assertSame( [ 'alpha', '99' ], $field[ 'classes' ] );
		$this->assertSame( [ 'auto_send' => '1' ], $field[ 'datas' ] );
		$this->assertSame( [ 'send_email' => '321' ], $field[ 'supp' ] );
	}

	public function test_login_field_base_rejects_invalid_provider_slug() :void {
		$this->expectException( ActionException::class );

		$action = new LoginIntentFormFieldBaseTestDouble( [
			'vars' => [
				'provider_slug' => 'bad-slug!',
				'field'         => [
					'name' => 'icwp_wpsf_email_otp',
					'type' => 'text',
				],
			],
		] );

		$action->checkAvailableDataForTest();
	}

	public function test_login_field_base_requires_field_data() :void {
		$this->expectException( ActionException::class );

		$action = new LoginIntentFormFieldBaseTestDouble( [
			'vars' => [
				'provider_slug' => 'email',
				'field'         => [],
			],
		] );

		$action->checkAvailableDataForTest();
	}

	public function test_base_form_builds_login_field_objects_and_filters_empty_provider_html() :void {
		$this->installMfaEnvironment(
			[
				new FakeEmailMfaProvider( '<input type="text" />' ),
				new FakeCustomMfaProvider( '<div>custom</div>' ),
				new FakeEmptyGaMfaProvider( '' ),
			],
			2,
			true
		);

		$action = new BaseFormTestDouble( $this->renderData( [
			'rememberme'  => 'forever',
			'redirect_to' => '/target',
		] ) );

		$data = $action->commonFormDataForTest();

		$this->assertFalse( $data[ 'flags' ][ 'show_branded_links' ] );
		$this->assertTrue( $data[ 'flags' ][ 'can_skip_mfa' ] );
		$this->assertArrayHasKey( 'skip_mfa', $data[ 'strings' ] ?? [] );
		$this->assertSame( '/target', $data[ 'vars' ][ 'form_hidden_fields' ][ 'redirect_to' ] );
		$this->assertSame( '/wp-login.php', $data[ 'vars' ][ 'form_hidden_fields' ][ 'cancel_href' ] );
		$this->assertSame( 42, $data[ 'vars' ][ 'form_hidden_fields' ][ 'wp_user_id' ] );
		$formActionParts = \wp_parse_url( $data[ 'hrefs' ][ 'form_action' ] );
		$formActionQuery = [];
		\parse_str( (string)( $formActionParts[ 'query' ] ?? '' ), $formActionQuery );
		$this->assertSame( '/wp-login.php', $formActionParts[ 'path' ] ?? '' );
		$this->assertSame( 'shield_action', $formActionQuery[ 'action' ] ?? '' );
		$this->assertSame( MfaLoginVerifyStep::SLUG, $formActionQuery[ 'ex' ] ?? '' );
		$this->assertArrayHasKey( 'exnonce', $formActionQuery );
		$this->assertIsString( $formActionQuery[ 'exnonce' ] );

		$fields = $data[ 'content' ][ 'login_fields' ];
		$this->assertCount( 2, $fields );

		$this->assertSame(
			[
				'slug'      => 'email',
				'name'      => 'Email',
				'html'      => '<input type="text" />',
				'tab_icon'  => 'bi-envelope',
				'tab_label' => 'Email',
			],
			$fields[ 0 ]
		);
		$this->assertSame(
			[
				'slug'      => 'customotp',
				'name'      => 'Custom OTP',
				'html'      => '<div>custom</div>',
				'tab_icon'  => 'bi-shield-lock',
				'tab_label' => 'Custom OTP',
			],
			$fields[ 1 ]
		);
	}

	public function test_base_form_emits_canonical_hidden_fields_and_literal_interim_global() :void {
		$this->installMfaEnvironment( [], 0, false );
		global $interim_login;
		$interim_login = true;

		$fields = ( new BaseFormTestDouble( $this->renderData( [
			'plain_login_nonce' => 'nonce',
			'rememberme'        => 'forever',
			'interim_login'     => '',
			'redirect_to'       => '/safe-target',
			'cancel_href'       => '/safe-cancel',
		] ) ) )->hiddenFieldsForTest();

		$this->assertSame( 42, $fields[ 'wp_user_id' ] );
		$this->assertSame( 'nonce', $fields[ 'login_nonce' ] );
		$this->assertSame( 'forever', $fields[ 'rememberme' ] );
		$this->assertSame( '1', $fields[ 'interim-login' ] );
		$this->assertSame( '/safe-target', $fields[ 'redirect_to' ] );
		$this->assertSame( '/safe-cancel', $fields[ 'cancel_href' ] );
	}

	public function test_base_form_ignores_non_string_referer_for_cancel_fallback() :void {
		$this->installMfaEnvironment( [], 0, false );
		ServicesState::mergeItems( [
			'service_request' => new class extends UnitTestRequest {
				public function server( $key, $default = null ) {
					return $key === 'HTTP_REFERER' ? [ 'invalid' ] : $default;
				}

				public function getPath() :string {
					return '/current-path';
				}
			},
		] );

		$fields = ( new BaseFormTestDouble( $this->renderData( [
			'redirect_to' => '/canonical-target',
		] ) ) )->hiddenFieldsForTest();

		$this->assertSame( '/canonical-target', $fields[ 'redirect_to' ] );
		$this->assertArrayNotHasKey( 'cancel_href', $fields );
	}

	public function test_base_form_ignores_unknown_positive_user_id() :void {
		$this->installMfaEnvironment( [], 0, false, false );

		$data = ( new BaseFormTestDouble( $this->renderData() ) )->commonFormDataForTest();

		$this->assertSame( [], $data[ 'content' ][ 'login_fields' ] );
	}

	public function test_login_intent_javascript_preserves_canonical_action_data_and_stable_keys() :void {
		$this->installMfaEnvironment( [], 0, false );

		$data = ( new BaseLoginIntentPageTestDouble( $this->renderData( [
			'plain_login_nonce' => 'nonce',
			'redirect_to'       => '/target',
		] ) ) )->getLoginIntentJavascript();

		$this->assertSame( 42, $data[ 'ajax' ][ 'passkey_auth_start' ][ 'login_wp_user' ] ?? null );
		$this->assertSame( 'nonce', $data[ 'ajax' ][ 'passkey_auth_start' ][ 'login_nonce' ] ?? null );
		$this->assertSame( 42, $data[ 'ajax' ][ 'email_code_send' ][ 'wp_user_id' ] ?? null );
		$this->assertSame( 'nonce', $data[ 'ajax' ][ 'email_code_send' ][ 'login_nonce' ] ?? null );
		$this->assertSame( '/target', $data[ 'ajax' ][ 'email_code_send' ][ 'redirect_to' ] ?? null );
		$this->assertFalse( $data[ 'flags' ][ 'passkey_auth_auto' ] );
	}

	public function test_shield_form_render_data_hides_alert_without_error_and_surfaces_error_message() :void {
		$this->installMfaEnvironment( [], 0, false );

		$defaultData = ( new LoginIntentFormShieldTestDouble( $this->renderData() ) )->renderDataForTest();
		$payload = '<img src=x onerror=alert(1)>';
		$errorData = ( new LoginIntentFormShieldTestDouble( $this->renderData( [
			'msg_error' => $payload,
		] ) ) )->renderDataForTest();

		$this->assertFalse( $defaultData[ 'flags' ][ 'show_message' ] );
		$this->assertSame( '', $defaultData[ 'strings' ][ 'message' ] );
		$this->assertSame(
			'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			$defaultData[ 'hrefs' ][ 'what_is_this' ]
		);
		$this->assertSame( '/images/banner.png', $defaultData[ 'imgs' ][ 'logo_banner' ] );

		$this->assertTrue( $errorData[ 'flags' ][ 'show_message' ] );
		$this->assertSame( $payload, $errorData[ 'strings' ][ 'message' ] );
	}

	private function renderData( array $input = [] ) :array {
		return LoginRequestValues::buildLoginIntentRenderData(
			\array_merge( [
				'user_id'           => 42,
				'include_body'      => true,
				'plain_login_nonce' => 'login-nonce',
			], $input ),
			'/current-path'
		);
	}

	private function installMfaEnvironment( array $providers, int $skipDays, bool $whitelabelEnabled, bool $userExists = true ) :Controller {
		ServicesState::installItems( [
			'service_request'   => new class extends UnitTestRequest {
				public function server( $key, $default = null ) {
					return $key === 'HTTP_REFERER'
						? 'http://example.com/wp-login.php?redirect_to=%2Ftarget'
						: $default;
				}

				public function getPath() :string {
					return '/current-path';
				}
			},
			'service_wpgeneral' => new class extends UnitTestGeneral {
				public function getLoginUrl( string $redirect = '' ) :string {
					return '/wp-login.php';
				}
			},
			'service_wpusers'   => new class( $userExists ) extends UnitTestUsers {
				private bool $userExists;

				public function __construct( bool $userExists ) {
					parent::__construct();
					$this->userExists = $userExists;
				}

				public function getUserById( $userId ) {
					if ( !$this->userExists ) {
						return null;
					}
					$user = new \WP_User();
					$user->ID = $userId;
					return $user;
				}
			},
			'service_data'      => new class extends \FernleafSystems\Wordpress\Services\Utilities\Data {
				public function isValidWebUrl( $url ) :bool {
					return \filter_var( $url, \FILTER_VALIDATE_URL ) !== false;
				}
			},
		] );

		return UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'opts'        => new class {
					public function optGet( string $key ) :string {
						return $key === 'mfa_verify_page' ? 'custom' : '';
					}
				},
				'labels'      => new class {
					public string $url_img_pagebanner = '/images/banner.png';
				},
				'comps'       => (object)[
					'mfa'        => new class( $providers, $skipDays ) {
						private array $providers;
						private int $skipDays;

						public function __construct( array $providers, int $skipDays ) {
							$this->providers = $providers;
							$this->skipDays = $skipDays;
						}

						public function getMfaSkip() :int {
							return $this->skipDays*\DAY_IN_SECONDS;
						}

						public function getProvidersActiveForUser( object $user ) :array {
							return $this->providers;
						}
					},
					'whitelabel' => new class( $whitelabelEnabled ) {
						private bool $enabled;

						public function __construct( bool $enabled ) {
							$this->enabled = $enabled;
						}

						public function isEnabled() :bool {
							return $this->enabled;
						}
					},
				],
				'plugin_urls' => new class {
					public function noncedPluginAction( string $action, ?string $url = null, array $aux = [] ) :string {
						return '/wp-login.php?action=shield_action&ex='.$action::SLUG.'&exnonce=nonce-'.$action::SLUG;
					}
				},
			]
		);
	}
}

class LoginIntentFormFieldBaseTestDouble extends LoginIntentFormFieldBase {

	public function checkAvailableDataForTest() :void {
		$this->checkAvailableData();
	}

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function exec() {
	}
}

class BaseFormTestDouble extends BaseForm {

	public const SLUG = 'unit_test_mfa_form';

	public function commonFormDataForTest() :array {
		return $this->getCommonFormData();
	}

	public function hiddenFieldsForTest() :array {
		return $this->getHiddenFields();
	}

	protected function exec() {
	}
}

class BaseLoginIntentPageTestDouble extends BaseLoginIntentPage {

	public const SLUG = 'unit_test_mfa_page';
	public const TEMPLATE = '/unit-test.twig';
}

class LoginIntentFormShieldTestDouble extends LoginIntentFormShield {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function exec() {
	}
}

class FakeMfaProvider {

	private string $html;

	public function __construct( string $html ) {
		$this->html = $html;
	}

	public function renderLoginIntentFormField( string $page ) :string {
		return $this->html;
	}
}

class FakeEmailMfaProvider extends FakeMfaProvider {

	public static function ProviderSlug() :string {
		return 'email';
	}

	public static function ProviderName() :string {
		return 'Email';
	}
}

class FakeCustomMfaProvider extends FakeMfaProvider {

	public static function ProviderSlug() :string {
		return 'customotp';
	}

	public static function ProviderName() :string {
		return 'Custom OTP';
	}
}

class FakeEmptyGaMfaProvider extends FakeMfaProvider {

	public static function ProviderSlug() :string {
		return 'ga';
	}

	public static function ProviderName() :string {
		return 'Authenticator';
	}
}
