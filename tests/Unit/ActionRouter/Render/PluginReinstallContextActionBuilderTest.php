<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginReinstall;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PluginReinstallContextActionBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\PluginReinstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestGeneral,
	UnitTestRequest,
	UnitTestUsers
};
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class PluginReinstallContextActionBuilderTest extends BaseUnitTest {

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

	public function test_build_for_plugin_file_returns_context_action_for_eligible_plugin() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new UnitTestRequest(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_wpplugins' => new PluginReinstallContextActionBuilderTestPluginsService( [
				'akismet/akismet.php' => new PluginReinstallContextActionBuilderTestPluginVo( 'akismet/akismet.php', true ),
			] ),
		] );

		$actions = ( new PluginReinstallContextActionBuilder( new PluginReinstaller() ) )
			->buildForPluginFile( 'akismet/akismet.php', 'Akismet' );

		$this->assertCount( 1, $actions );
		$this->assertSame( 'ajax', $actions[ 0 ][ 'kind' ] ?? '' );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( 'bi bi-arrow-clockwise', $actions[ 0 ][ 'icon_class' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'label' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'confirm_text' ] ?? '' );

		$actionData = \json_decode( (string)( $actions[ 0 ][ 'ajax_action_json' ] ?? '' ), true, 512, \JSON_THROW_ON_ERROR );
		$this->assertSame( PluginReinstall::SLUG, $actionData[ 'ex' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $actionData[ 'file' ] ?? '' );
		$this->assertArrayNotHasKey( 'reinstall', $actionData );
	}

	public function test_build_for_plugin_file_returns_empty_actions_for_ineligible_plugin() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new UnitTestRequest(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_wpplugins' => new PluginReinstallContextActionBuilderTestPluginsService( [
				'premium/plugin.php' => new PluginReinstallContextActionBuilderTestPluginVo( 'premium/plugin.php', false ),
				'update/plugin.php'  => new PluginReinstallContextActionBuilderTestPluginVo( 'update/plugin.php', true ),
			], [
				'update/plugin.php' => true,
			] ),
		] );

		$builder = new PluginReinstallContextActionBuilder( new PluginReinstaller() );

		$this->assertSame( [], $builder->buildForPluginFile( 'missing/plugin.php' ) );
		$this->assertSame( [], $builder->buildForPluginFile( 'premium/plugin.php' ) );
		$this->assertSame( [], $builder->buildForPluginFile( 'update/plugin.php' ) );
	}
}

class PluginReinstallContextActionBuilderTestPluginsService extends Plugins {

	private array $pluginVos;

	private array $updates;

	public function __construct( array $pluginVos, array $updates = [] ) {
		$this->pluginVos = $pluginVos;
		$this->updates = $updates;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->pluginVos[ $file ] ?? null;
	}

	public function isUpdateAvailable( $file ) :bool {
		return !empty( $this->updates[ $file ] );
	}
}

class PluginReinstallContextActionBuilderTestPluginVo extends WpPluginVo {

	public string $file;
	public string $Name;
	public string $Title;

	private bool $isWpOrg;

	public function __construct( string $file, bool $isWpOrg ) {
		$this->file = $file;
		$this->Name = 'Test Plugin';
		$this->Title = 'Test Plugin';
		$this->isWpOrg = $isWpOrg;
	}

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'plugin' : ( $this->{$key} ?? null );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
	}
}
