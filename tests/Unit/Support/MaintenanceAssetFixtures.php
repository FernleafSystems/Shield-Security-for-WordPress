<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	General,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Utilities\Data;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

trait MaintenanceAssetFixtures {

	/**
	 * @return array<string,object>
	 */
	protected function buildMaintenanceAssetServiceItems( array $pluginFixture = [], array $themeFixture = [] ) :array {
		$pluginFixture = \array_merge( [
			'updates'       => [],
			'plugins'       => [],
			'active'        => [],
			'plugin_vos'    => [],
			'activate_urls' => [],
			'upgrade_urls'  => [],
		], $pluginFixture );
		$themeFixture = \array_merge( [
			'updates'        => [],
			'themes'         => [],
			'theme_vos'      => [],
			'current'        => '',
			'current_parent' => '',
		], $themeFixture );

		return [
			'service_data' => new class extends Data {
				public function getPhpVersionIsAtLeast( string $minimumVersion ) :bool {
					return true;
				}

				public function getPhpVersionCleaned( bool $excludeMinor = false ) :string {
					return '8.2';
				}

				public function isWindows() :bool {
					return false;
				}
			},
			'service_wpfs' => new class extends Fs {
				public function isAccessibleFile( string $path ) :bool {
					return false;
				}
			},
			'service_wpgeneral' => new class extends General {
				public function getAdminUrl( string $path = '', bool $wpmsOnly = false ) :string {
					return '/wp-admin/'.\ltrim( $path, '/' );
				}

				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}

				public function hasCoreUpdate() :bool {
					return false;
				}

				public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
					return $mDefault;
				}

				public function getAdminUrl_Updates( bool $bWpmsOnly = false ) :string {
					return '/wp-admin/update-core.php';
				}

				public function getAdminUrl_Plugins( bool $wpmsOnly = false ) :string {
					return '/wp-admin/plugins.php';
				}

				public function getAdminUrl_Themes( bool $wpmsOnly = false ) :string {
					return '/wp-admin/themes.php';
				}

				public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
					return 'http://example.com/'.\ltrim( $path, '/' );
				}

				public function getWpUrl( string $path = '' ) :string {
					return 'http://example.com/'.\ltrim( $path, '/' );
				}
			},
			'service_wpplugins' => new class( $pluginFixture ) extends Plugins {
				private array $fixture;

				public function __construct( array $fixture ) {
					$this->fixture = $fixture;
				}

				public function getUpdates( $bForceUpdateCheck = false ) {
					return $this->fixture[ 'updates' ];
				}

				public function getPlugins() :array {
					return $this->fixture[ 'plugins' ];
				}

				public function getActivePlugins() :array {
					return $this->fixture[ 'active' ];
				}

				public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
					return $this->fixture[ 'plugin_vos' ][ $file ] ?? null;
				}

				public function getUrl_Activate( $file ) :string {
					return (string)( $this->fixture[ 'activate_urls' ][ $file ] ?? '/wp-admin/plugins.php?action=activate&plugin='.$file );
				}

				public function getUrl_Upgrade( $file ) :string {
					return (string)( $this->fixture[ 'upgrade_urls' ][ $file ] ?? '/wp-admin/update.php?action=upgrade-plugin&plugin='.$file );
				}
			},
			'service_wpthemes' => new class( $themeFixture ) extends Themes {
				private array $fixture;

				public function __construct( array $fixture ) {
					$this->fixture = $fixture;
				}

				public function getUpdates( $forceCheck = false ) {
					return $this->fixture[ 'updates' ];
				}

				public function getThemes() :array {
					return $this->fixture[ 'themes' ];
				}

				public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
					return $this->fixture[ 'theme_vos' ][ $stylesheet ] ?? null;
				}

				public function getCurrent() {
					return new class( $this->fixture[ 'current' ], $this->fixture[ 'current_parent' ] ) {
						private string $stylesheet;
						private string $template;

						public function __construct( string $stylesheet, string $template ) {
							$this->stylesheet = $stylesheet;
							$this->template = $template !== '' ? $template : $stylesheet;
						}

						public function get_stylesheet() :string {
							return $this->stylesheet;
						}

						public function get_template() :string {
							return $this->template;
						}
					};
				}

				public function getCurrentParent() {
					if ( $this->fixture[ 'current_parent' ] === '' ) {
						return null;
					}

					return new class( $this->fixture[ 'current_parent' ] ) {
						private string $stylesheet;

						public function __construct( string $stylesheet ) {
							$this->stylesheet = $stylesheet;
						}

						public function get_stylesheet() :string {
							return $this->stylesheet;
						}

						public function get_template() :string {
							return $this->stylesheet;
						}
					};
				}
			},
		];
	}

	protected function buildMaintenancePluginVo( string $file, string $title, string $version ) :WpPluginVo {
		return new class( $file, $title, $version ) extends WpPluginVo {
			public string $file;
			public string $Title;
			public string $Name;
			public string $Version;

			public function __construct( string $file, string $title, string $version ) {
				$this->file = $file;
				$this->Title = $title;
				$this->Name = $title;
				$this->Version = $version;
			}
		};
	}

	protected function buildMaintenanceThemeVo( string $stylesheet, string $name, string $version ) :WpThemeVo {
		return new class( $stylesheet, $name, $version ) extends WpThemeVo {
			public string $stylesheet;
			public string $Name;
			public string $Version;

			public function __construct( string $stylesheet, string $name, string $version ) {
				$this->stylesheet = $stylesheet;
				$this->Name = $name;
				$this->Version = $version;
			}
		};
	}
}
