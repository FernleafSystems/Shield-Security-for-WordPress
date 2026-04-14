<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa\LoginIntent\LoginIntentFormFieldBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components\{
	BaseForm,
	LoginIntentFormShield
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
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

		$action = new BaseFormTestDouble( [
			'user_id'           => 42,
			'plain_login_nonce' => 'login-nonce',
			'rememberme'        => 'Y',
		] );

		$data = $action->commonFormDataForTest();

		$this->assertFalse( $data[ 'flags' ][ 'show_branded_links' ] );
		$this->assertTrue( $data[ 'flags' ][ 'can_skip_mfa' ] );
		$this->assertSame( 'Remember me for 2 days', $data[ 'strings' ][ 'skip_mfa' ] );
		$this->assertSame( '/target', $data[ 'vars' ][ 'form_hidden_fields' ][ 'redirect_to' ] );
		$this->assertSame( '/wp-login.php', $data[ 'vars' ][ 'form_hidden_fields' ][ 'cancel_href' ] );
		$this->assertSame( 42, $data[ 'vars' ][ 'form_hidden_fields' ][ 'wp_user_id' ] );
		$this->assertStringStartsWith( '/wp-login.php?', $data[ 'hrefs' ][ 'form_action' ] );
		$this->assertStringContainsString( 'action=shield_action', $data[ 'hrefs' ][ 'form_action' ] );
		$this->assertStringContainsString( 'ex=', $data[ 'hrefs' ][ 'form_action' ] );
		$this->assertStringContainsString( 'exnonce=', $data[ 'hrefs' ][ 'form_action' ] );

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

	public function test_shield_form_render_data_hides_alert_without_error_and_surfaces_error_message() :void {
		$this->installMfaEnvironment( [], 0, false );

		$defaultData = ( new LoginIntentFormShieldTestDouble( [] ) )->renderDataForTest();
		$errorData = ( new LoginIntentFormShieldTestDouble( [
			'msg_error' => 'Could not verify your 2FA codes',
		] ) )->renderDataForTest();

		$this->assertFalse( $defaultData[ 'flags' ][ 'show_message' ] );
		$this->assertSame( '', $defaultData[ 'strings' ][ 'message' ] );
		$this->assertSame(
			'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			$defaultData[ 'hrefs' ][ 'what_is_this' ]
		);
		$this->assertSame( '/images/banner.png', $defaultData[ 'imgs' ][ 'logo_banner' ] );

		$this->assertTrue( $errorData[ 'flags' ][ 'show_message' ] );
		$this->assertSame( 'Could not verify your 2FA codes', $errorData[ 'strings' ][ 'message' ] );
	}

	private function installMfaEnvironment( array $providers, int $skipDays, bool $whitelabelEnabled ) :Controller {
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
			'service_wpusers'   => new class extends UnitTestUsers {
				public function getUserById( $userId ) {
					return (object)[ 'ID' => $userId ];
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

	protected function exec() {
	}
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
