<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis\OriginalFileSource;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	General,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class ItemAnalysisOriginalFileSourceTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
		Functions\when( 'get_theme_root' )->alias( static fn() :string => \str_replace( '\\', '/', WP_CONTENT_DIR ).'/themes' );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpfs'      => new class extends Fs {
				public function isAbsPath( $path ) {
					return true;
				}
			},
			'service_wpgeneral' => new class extends General {
				public function getVersion( $ignoreClassicpress = false ) :string {
					return '6.8.1';
				}
			},
			'service_wpplugins' => new OriginalFileSourcePlugins( [
				'akismet/akismet.php' => OriginalFileSourcePluginVo::make( 'Akismet', '5.3', true ),
				'edd-pro/edd-pro.php' => OriginalFileSourcePluginVo::make( 'Easy Digital Downloads Pro', '3.7.2', false ),
			] ),
			'service_wpthemes'  => new OriginalFileSourceThemes( OriginalFileSourceThemeVo::make( 'divi', 'Divi', '4.27.4' ) ),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_wordpress_org_plugin_file() :void {
		$source = OriginalFileSource::forItem( $this->pluginItem( 'akismet/class.akismet.php' ) );

		$this->assertSame( OriginalFileSource::KIND_WPORG_PLUGIN, $source->kind );
		$this->assertSame( 'Akismet 5.3 (plugin from WordPress.org)', $source->label() );
	}

	public function test_premium_plugin_file() :void {
		$source = OriginalFileSource::forItem( $this->pluginItem( 'edd-pro/includes/class-edd.php' ) );

		$this->assertSame( OriginalFileSource::KIND_PREMIUM_PLUGIN, $source->kind );
		$this->assertSame( 'Easy Digital Downloads Pro 3.7.2 (premium or custom plugin)', $source->label() );
	}

	public function test_theme_file_is_resolved_without_asking_wordpress_org() :void {
		$item = new ResultItem();
		$item->path_fragment = 'wp-content/themes/divi/functions.php';
		$item->path_full = \str_replace( '\\', '/', WP_CONTENT_DIR ).'/themes/divi/functions.php';
		$item->is_in_theme = true;

		$source = OriginalFileSource::forItem( $item );

		$this->assertSame( OriginalFileSource::KIND_THEME, $source->kind );
		$this->assertSame( 'Divi 4.27.4 (theme)', $source->label() );
	}

	public function test_core_file_uses_the_installed_wordpress_version() :void {
		$item = new ResultItem();
		$item->path_fragment = 'wp-includes/version.php';
		$item->path_full = '/var/www/wp-includes/version.php';
		$item->is_in_core = true;

		$source = OriginalFileSource::forItem( $item );

		$this->assertSame( OriginalFileSource::KIND_CORE, $source->kind );
		$this->assertSame( 'WordPress 6.8.1 core', $source->label() );
	}

	public function test_unknown_plugin_and_root_files_have_no_source() :void {
		$rootFile = new ResultItem();
		$rootFile->path_fragment = 'stray.php';
		$rootFile->path_full = '/var/www/stray.php';
		$rootFile->is_in_core = true;
		$rootFile->is_in_wproot = true;

		foreach ( [ $this->pluginItem( 'removed-plugin/file.php' ), $rootFile ] as $item ) {
			$source = OriginalFileSource::forItem( $item );
			$this->assertSame( OriginalFileSource::KIND_NONE, $source->kind );
			$this->assertSame( '', $source->label() );
		}
	}

	private function pluginItem( string $pluginRelativePath ) :ResultItem {
		$item = new ResultItem();
		$item->path_fragment = 'wp-content/plugins/'.$pluginRelativePath;
		$item->path_full = \str_replace( '\\', '/', WP_PLUGIN_DIR ).'/'.$pluginRelativePath;
		$item->is_in_plugin = true;
		return $item;
	}
}

class OriginalFileSourcePlugins extends Plugins {

	/** @var array<string,WpPluginVo> */
	private array $plugins;

	public function __construct( array $plugins ) {
		$this->plugins = $plugins;
	}

	public function getInstalledPluginFiles() :array {
		return \array_keys( $this->plugins );
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->plugins[ $file ] ?? null;
	}
}

class OriginalFileSourcePluginVo extends WpPluginVo {

	private bool $wpOrg = false;

	public function __construct() {
	}

	public static function make( string $name, string $version, bool $wpOrg ) :self {
		$vo = new self();
		$vo->Name = $name;
		$vo->Version = $version;
		$vo->wpOrg = $wpOrg;
		return $vo;
	}

	public function isWpOrg() :bool {
		return $this->wpOrg;
	}
}

class OriginalFileSourceThemes extends Themes {

	private WpThemeVo $theme;

	public function __construct( WpThemeVo $theme ) {
		$this->theme = $theme;
	}

	public function getThemes() :array {
		return [ $this->theme->wp_theme ];
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		return $stylesheet === $this->theme->stylesheet ? $this->theme : null;
	}
}

class OriginalFileSourceThemeVo extends WpThemeVo {

	public function __construct() {
	}

	public static function make( string $stylesheet, string $name, string $version ) :self {
		$vo = new self();
		$vo->stylesheet = $stylesheet;
		$vo->wp_theme = new class( $stylesheet, [ 'Name' => $name, 'Version' => $version ] ) {

			private string $stylesheet;

			private array $headers;

			public function __construct( string $stylesheet, array $headers ) {
				$this->stylesheet = $stylesheet;
				$this->headers = $headers;
			}

			public function get_stylesheet() :string {
				return $this->stylesheet;
			}

			public function get( string $header ) {
				return $this->headers[ $header ] ?? false;
			}
		};
		return $vo;
	}

	public function isWpOrg() :bool {
		throw new \LogicException( 'The Overview must not ask WordPress.org about themes.' );
	}
}
