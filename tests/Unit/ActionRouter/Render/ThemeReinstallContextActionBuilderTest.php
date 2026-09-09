<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ThemeReinstallContextActionBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ThemeReinstall;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\ThemeReinstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestGeneral,
	UnitTestRequest,
	UnitTestUsers
};
use FernleafSystems\Wordpress\Services\Core\Themes;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class ThemeReinstallContextActionBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				return \is_array( $value )
					? \array_map( static fn( $item ) :string => \rawurlencode( (string)$item ), $value )
					: \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_for_theme_stylesheet_returns_context_action_for_eligible_theme() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new UnitTestRequest(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_wpthemes'  => new ThemeReinstallContextActionBuilderTestThemesService( [
				'twentytwentyfive' => new ThemeReinstallContextActionBuilderTestThemeVo( 'twentytwentyfive', true ),
			] ),
		] );

		$actions = ( new ThemeReinstallContextActionBuilder( new ThemeReinstaller() ) )
			->buildForThemeStylesheet( 'twentytwentyfive', 'Twenty Twenty-Five' );

		$this->assertCount( 1, $actions );
		$this->assertSame( 'ajax', $actions[ 0 ][ 'kind' ] ?? '' );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( 'bi bi-arrow-clockwise', $actions[ 0 ][ 'icon_class' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'label' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'confirm_text' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'processing_text' ] ?? '' );
		$this->assertStringContainsString( 'Twenty Twenty-Five', $actions[ 0 ][ 'processing_text' ] ?? '' );

		$actionData = \json_decode( (string)( $actions[ 0 ][ 'ajax_action_json' ] ?? '' ), true, 512, \JSON_THROW_ON_ERROR );
		$this->assertSame( ThemeReinstall::SLUG, $actionData[ 'ex' ] ?? '' );
		$this->assertSame( 'twentytwentyfive', $actionData[ 'stylesheet' ] ?? '' );
		$this->assertArrayNotHasKey( 'reinstall', $actionData );
	}

	public function test_build_for_theme_stylesheet_returns_empty_actions_for_ineligible_theme() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new UnitTestRequest(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_wpthemes'  => new ThemeReinstallContextActionBuilderTestThemesService( [
				'premium-theme' => new ThemeReinstallContextActionBuilderTestThemeVo( 'premium-theme', false ),
			] ),
		] );

		$builder = new ThemeReinstallContextActionBuilder( new ThemeReinstaller() );

		$this->assertSame( [], $builder->buildForThemeStylesheet( 'missing-theme' ) );
		$this->assertSame( [], $builder->buildForThemeStylesheet( 'premium-theme' ) );
	}

	public function test_build_for_theme_stylesheet_returns_update_link_for_wporg_theme_with_pending_update() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new UnitTestRequest(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_wpthemes'  => new ThemeReinstallContextActionBuilderTestThemesService( [
				'update-theme' => new ThemeReinstallContextActionBuilderTestThemeVo( 'update-theme', true ),
			], [
				'update-theme' => true,
			] ),
		] );

		$actions = ( new ThemeReinstallContextActionBuilder( new ThemeReinstaller() ) )
			->buildForThemeStylesheet( 'update-theme', 'Update Theme' );

		$this->assertCount( 1, $actions );
		$this->assertSame( 'href', $actions[ 0 ][ 'kind' ] ?? '' );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( 'bi bi-arrow-up-circle-fill', $actions[ 0 ][ 'icon_class' ] ?? '' );
		$this->assertSame( '/wp-admin/update-core.php', $actions[ 0 ][ 'href' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'label' ] ?? '' );
		$this->assertArrayNotHasKey( 'ajax_action_json', $actions[ 0 ] );
	}
}

class ThemeReinstallContextActionBuilderTestThemesService extends Themes {

	private array $themeVos;

	private array $updates;

	public function __construct( array $themeVos, array $updates = [] ) {
		$this->themeVos = $themeVos;
		$this->updates = $updates;
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		return $this->themeVos[ $stylesheet ] ?? null;
	}

	public function isUpdateAvailable( string $slug ) :bool {
		return !empty( $this->updates[ $slug ] );
	}
}

class ThemeReinstallContextActionBuilderTestThemeVo extends WpThemeVo {

	public string $stylesheet;
	public string $Name;

	private bool $isWpOrg;

	public function __construct( string $stylesheet, bool $isWpOrg ) {
		$this->stylesheet = $stylesheet;
		$this->Name = 'Test Theme';
		$this->isWpOrg = $isWpOrg;
	}

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'theme' : ( $this->{$key} ?? null );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
	}
}
