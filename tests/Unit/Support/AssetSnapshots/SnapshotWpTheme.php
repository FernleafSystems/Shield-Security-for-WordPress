<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

class SnapshotWpTheme {

	private SnapshotThemeVo $theme;

	public function __construct( SnapshotThemeVo $theme ) {
		$this->theme = $theme;
	}

	public function get( string $key ) :string {
		return $key === 'Version' ? $this->theme->Version : $this->theme->Name;
	}

	public function get_stylesheet() :string {
		return $this->theme->stylesheet;
	}

	public function get_template() :string {
		return $this->theme->stylesheet;
	}

	public function get_stylesheet_directory() :string {
		return \str_replace( '\\', '/', \rtrim( WP_CONTENT_DIR.'/themes/'.$this->theme->stylesheet, '/\\' ) );
	}
}
