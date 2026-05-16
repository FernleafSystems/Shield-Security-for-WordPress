<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class SnapshotPluginVo extends WpPluginVo {

	public string $file;
	public string $Name;
	public string $Version;
	public bool $active = true;

	public function __construct( string $file, string $version, string $name = 'Snapshot Plugin' ) {
		$this->file = $file;
		$this->Version = $version;
		$this->Name = $name;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'plugin';
			case 'slug':
				return \dirname( $this->file );
			case 'unique_id':
				return $this->file;
			case 'version':
				return $this->Version;
			default:
				return $this->{$key} ?? null;
		}
	}

	public function getInstallDir() :string {
		return \str_replace( '\\', '/', \rtrim( \dirname( WP_PLUGIN_DIR.'/'.$this->file ), '/\\' ) ).'/';
	}

	public function isWpOrg() :bool {
		return false;
	}
}
