<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\Themes;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class MaintenanceThemesService extends Themes {

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
		return new MaintenanceThemeSelection(
			$this->fixture[ 'current' ],
			$this->fixture[ 'current_parent' ]
		);
	}

	public function getCurrentParent() {
		if ( $this->fixture[ 'current_parent' ] === '' ) {
			return null;
		}

		return new MaintenanceThemeSelection(
			$this->fixture[ 'current_parent' ],
			$this->fixture[ 'current_parent' ]
		);
	}
}
