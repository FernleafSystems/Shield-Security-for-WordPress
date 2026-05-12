<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\General;

class UnitTestGeneral extends General {

	private string $ajaxUrl;

	private string $displayTimePrefix;

	public function __construct(
		string $ajaxUrl = '/admin-ajax.php',
		string $displayTimePrefix = 'display:'
	) {
		$this->ajaxUrl = $ajaxUrl;
		$this->displayTimePrefix = $displayTimePrefix;
	}

	public function ajaxURL() :string {
		return $this->ajaxUrl;
	}

	public function getAdminUrl( string $path = '', bool $wpmsOnly = false ) :string {
		return '/wp-admin/'.\ltrim( $path, '/' );
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

	public function hasCoreUpdate() :bool {
		return false;
	}

	public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
		return $mDefault;
	}

	public function getTimeStringForDisplay( $ts = null, $bShowTime = true, $bShowDate = true ) {
		return $this->displayTimePrefix.(int)$ts;
	}
}
