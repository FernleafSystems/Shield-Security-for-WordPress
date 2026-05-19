<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class SnapshotThemeVo extends WpThemeVo {

	public string $stylesheet;
	public string $Name;
	public string $Version;
	public bool $active = true;

	public function __construct( string $stylesheet, string $version, string $name = 'Snapshot Theme' ) {
		$this->stylesheet = $stylesheet;
		$this->Version = $version;
		$this->Name = $name;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'theme';
			case 'slug':
			case 'unique_id':
				return $this->stylesheet;
			case 'version':
				return $this->Version;
			case 'is_child':
				return false;
			case 'wp_theme':
				return new SnapshotWpTheme( $this );
			default:
				return $this->{$key} ?? null;
		}
	}

	public function getInstallDir() :string {
		return \str_replace( '\\', '/', \rtrim( WP_CONTENT_DIR.'/themes/'.$this->stylesheet, '/\\' ) ).'/';
	}

	public function isWpOrg() :bool {
		return false;
	}
}
