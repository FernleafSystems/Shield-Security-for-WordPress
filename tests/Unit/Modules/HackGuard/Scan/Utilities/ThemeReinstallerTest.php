<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange\Cleanup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\ThemeReinstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Themes;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class ThemeReinstallerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_eligible_theme_requires_installed_wporg_theme_without_pending_update() :void {
		$eligibleTheme = new ThemeReinstallerTestThemeVo( 'twentytwentyfive', true );
		$updateTheme = new ThemeReinstallerTestThemeVo( 'update-theme', true );
		ServicesState::installItems( [
			'service_wpthemes' => new ThemeReinstallerTestThemesService( [
				'theme_vos' => [
					'twentytwentyfive' => $eligibleTheme,
					'premium-theme'    => new ThemeReinstallerTestThemeVo( 'premium-theme', false ),
					'update-theme'     => $updateTheme,
				],
				'updates'   => [
					'update-theme' => (object)[ 'new_version' => '2.0' ],
				],
			] ),
		] );

		$reinstaller = new ThemeReinstaller();

		$this->assertSame( $eligibleTheme, $reinstaller->eligibleTheme( 'twentytwentyfive' ) );
		$this->assertNull( $reinstaller->eligibleTheme( 'missing-theme' ) );
		$this->assertNull( $reinstaller->eligibleTheme( 'premium-theme' ) );
		$this->assertNull( $reinstaller->eligibleTheme( 'update-theme' ) );
		$this->assertSame( $eligibleTheme, $reinstaller->wpOrgTheme( 'twentytwentyfive' ) );
		$this->assertSame( $updateTheme, $reinstaller->wpOrgTheme( 'update-theme' ) );
		$this->assertNull( $reinstaller->wpOrgTheme( 'premium-theme' ) );
	}

	public function test_reinstall_runs_only_for_eligible_theme() :void {
		$themes = new ThemeReinstallerTestThemesService( [
			'theme_vos' => [
				'twentytwentyfive' => new ThemeReinstallerTestThemeVo( 'twentytwentyfive', true ),
				'update-theme'     => new ThemeReinstallerTestThemeVo( 'update-theme', true ),
				'fail-theme'       => new ThemeReinstallerTestThemeVo( 'fail-theme', true ),
			],
			'updates'   => [
				'update-theme' => (object)[ 'new_version' => '2.0' ],
			],
			'reinstall_results' => [
				'fail-theme' => false,
			],
		] );
		ServicesState::installItems( [
			'service_wpthemes' => $themes,
		] );

		$cleanup = new ThemeReinstallerTestCleanup();
		$reinstaller = new ThemeReinstallerTestSubject( $cleanup );

		$this->assertTrue( $reinstaller->reinstall( 'twentytwentyfive' ) );
		$this->assertFalse( $reinstaller->reinstall( 'update-theme' ) );
		$this->assertFalse( $reinstaller->reinstall( 'fail-theme' ) );
		$this->assertSame( [ 'twentytwentyfive', 'fail-theme' ], $themes->reinstallCalls );
		$this->assertSame( [ 'twentytwentyfive' ], $reinstaller->snapshotDeletes );
		$this->assertSame( [ [ 'theme', 'twentytwentyfive', 0 ] ], $cleanup->runs );
	}
}

class ThemeReinstallerTestSubject extends ThemeReinstaller {

	public array $snapshotDeletes = [];

	protected function deleteSnapshot( WpThemeVo $theme ) :void {
		$this->snapshotDeletes[] = $theme->stylesheet;
	}
}

class ThemeReinstallerTestCleanup extends Cleanup {

	public array $runs = [];

	public function run( string $assetType, string $assetKey, int $retry = 0 ) :void {
		$this->runs[] = [ $assetType, $assetKey, $retry ];
	}
}

class ThemeReinstallerTestThemesService extends Themes {

	public array $reinstallCalls = [];

	private array $fixture;

	public function __construct( array $fixture ) {
		$this->fixture = \array_merge( [
			'theme_vos'         => [],
			'updates'           => [],
			'reinstall_results' => [],
		], $fixture );
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		return $this->fixture[ 'theme_vos' ][ $stylesheet ] ?? null;
	}

	public function isUpdateAvailable( string $slug ) :bool {
		return isset( $this->fixture[ 'updates' ][ $slug ] );
	}

	public function reinstall( $slug, $bUseBackup = false ) {
		$this->reinstallCalls[] = $slug;
		return $this->fixture[ 'reinstall_results' ][ $slug ] ?? true;
	}
}

class ThemeReinstallerTestThemeVo extends WpThemeVo {

	public string $stylesheet;
	public string $Name;

	private bool $isWpOrg;

	public function __construct( string $stylesheet, bool $isWpOrg ) {
		$this->stylesheet = $stylesheet;
		$this->Name = 'Test Theme';
		$this->isWpOrg = $isWpOrg;
	}

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'theme' : ( $this->{$key} ?? null );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
	}
}
