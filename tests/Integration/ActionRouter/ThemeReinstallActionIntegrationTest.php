<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ThemeReinstall,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Themes;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class ThemeReinstallActionIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_reinstall_requires_valid_nonce_before_theme_lookup() :void {
		$themes = new ThemeReinstallPoisonedThemesService();
		$servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpthemes' => $themes,
		] );
		$snapshot = $this->seedActionNonceContext( ThemeReinstall::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => '',
		] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			( new ActionProcessor() )->processAction( ThemeReinstall::SLUG, [
				'stylesheet' => 'twentytwentyfive',
			] );
		}
		finally {
			$themeLookupAttempted = $themes->wasThemeLookupAttempted();
			$this->restoreActionNonceContext( $snapshot );
			ServicesState::restore( $servicesSnapshot );
			$this->assertFalse( $themeLookupAttempted );
		}
	}
}

class ThemeReinstallPoisonedThemesService extends Themes {

	private bool $themeLookupAttempted = false;

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $stylesheet, $reload );
		$this->themeLookupAttempted = true;
		throw new \RuntimeException( 'Theme reinstall nonce failure must not look up themes.' );
	}

	public function wasThemeLookupAttempted() :bool {
		return $this->themeLookupAttempted;
	}
}
