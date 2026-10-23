<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

/**
 * What a ruleset build needs to know about the site's plugins and themes.
 */
class SiteState {

	/**
	 * @param string[]              $activePlugins  plugin files, including network-active plugins
	 * @param string[]              $activeThemes   the active theme's stylesheet and template
	 * @param array<string, string> $pluginVersions installed plugin file => version
	 * @param array<string, string> $themeVersions  installed theme directory => version
	 */
	public function __construct(
		public readonly array $activePlugins,
		public readonly array $activeThemes,
		public readonly array $pluginVersions,
		public readonly array $themeVersions
	) {
	}

	/**
	 * Every installed plugin and theme as rule files name them: a plugin by its directory, or by its file name when
	 * it is a single file, and a theme by its directory.
	 *
	 * @return array<string, array{kind: string, slug: string}> by "kind/slug"
	 */
	public function installedAssets() :array {
		$assets = [];
		foreach ( \array_keys( $this->pluginVersions ) as $file ) {
			$dir = \dirname( (string)$file );
			$slug = $dir === '.' ? \basename( (string)$file, '.php' ) : $dir;
			$assets[ 'plugin/'.$slug ] = [ 'kind' => 'plugin', 'slug' => $slug ];
		}
		foreach ( \array_keys( $this->themeVersions ) as $theme ) {
			$assets[ 'theme/'.$theme ] = [ 'kind' => 'theme', 'slug' => (string)$theme ];
		}
		return $assets;
	}

	public function installedVersion( string $kind, string $slug ) :?string {
		if ( $kind === 'theme' ) {
			return $this->themeVersions[ $slug ] ?? null;
		}
		foreach ( $this->pluginVersions as $file => $version ) {
			$dir = \dirname( (string)$file );
			if ( $dir === $slug || ( $dir === '.' && \basename( (string)$file, '.php' ) === $slug ) ) {
				return (string)$version;
			}
		}
		return null;
	}
}
