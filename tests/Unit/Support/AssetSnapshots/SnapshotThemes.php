<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

use FernleafSystems\Wordpress\Services\Core\Themes;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class SnapshotThemes extends Themes {

	/**
	 * @var SnapshotThemeVo[]
	 */
	private array $themes;

	/**
	 * @param SnapshotThemeVo[] $themes
	 */
	public function __construct( array $themes ) {
		$this->themes = $themes;
	}

	/**
	 * @return SnapshotWpTheme[]
	 */
	public function getThemes() :array {
		return \array_map(
			static fn( SnapshotThemeVo $theme ) => new SnapshotWpTheme( $theme ),
			$this->themes
		);
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $reload );
		foreach ( $this->themes as $theme ) {
			if ( $theme->stylesheet === $stylesheet ) {
				return $theme;
			}
		}
		return null;
	}

	public function getCurrent() {
		return new SnapshotWpTheme( $this->themes[ 0 ] ?? new SnapshotThemeVo( 'missing-current-theme', '0.0.0' ) );
	}

	public function isActiveThemeAChild() :bool {
		return false;
	}
}
