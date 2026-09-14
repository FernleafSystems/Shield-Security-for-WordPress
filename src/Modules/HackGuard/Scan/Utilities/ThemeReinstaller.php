<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange\Cleanup;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class ThemeReinstaller {

	private Cleanup $assetCleanup;

	public function __construct( ?Cleanup $assetCleanup = null ) {
		$this->assetCleanup = $assetCleanup ?? new Cleanup();
	}

	public function eligibleTheme( string $stylesheet ) :?WpThemeVo {
		$theme = $this->wpOrgTheme( $stylesheet );
		if ( !$theme instanceof WpThemeVo || Services::WpThemes()->isUpdateAvailable( $theme->stylesheet ) ) {
			return null;
		}

		return $theme;
	}

	public function wpOrgTheme( string $stylesheet ) :?WpThemeVo {
		$stylesheet = \trim( $stylesheet );
		if ( $stylesheet === '' ) {
			return null;
		}

		$themes = Services::WpThemes();
		$theme = $themes->getThemeAsVo( $stylesheet );
		if ( !$theme instanceof WpThemeVo
			 || $theme->asset_type !== 'theme'
			 || !$theme->isWpOrg() ) {
			return null;
		}

		return $theme;
	}

	public function reinstall( string $stylesheet ) :bool {
		$theme = $this->eligibleTheme( $stylesheet );
		if ( !$theme instanceof WpThemeVo || !Services::WpThemes()->reinstall( $theme->stylesheet ) ) {
			return false;
		}

		$this->deleteSnapshot( $theme );
		$this->assetCleanup->schedule( 'theme', $theme->stylesheet );

		return true;
	}

	protected function deleteSnapshot( WpThemeVo $theme ) :void {
		( new Delete() )
			->setAsset( $theme )
			->run();
	}
}
