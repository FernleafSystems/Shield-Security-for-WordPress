<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsThemeActive;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenanceThemeSelection,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Themes;

class IsThemeActiveTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\stubTranslationFunctions();

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpthemes' => new class extends Themes {
				public function getExists( string $stylesheet ) :bool {
					return \in_array( $stylesheet, [ 'is-theme-active-current', 'is-theme-active-other' ], true );
				}

				public function getCurrent() {
					return new MaintenanceThemeSelection( 'is-theme-active-current', '' );
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testActiveThemeMatches() :void {
		$this->assertTrue( $this->buildCondition( 'is-theme-active-current' )->run() );
	}

	public function testInstalledInactiveThemeDoesNotMatch() :void {
		$this->assertFalse( $this->buildCondition( 'is-theme-active-other' )->run() );
	}

	public function testMissingThemeDoesNotMatch() :void {
		$this->assertFalse( $this->buildCondition( 'is-theme-active-missing' )->run() );
	}

	private function buildCondition( string $themeDir ) :IsThemeActive {
		$condition = new IsThemeActive();
		$condition->setThisRequest( new class extends ThisRequest {
			public function __construct() {
			}
		} );
		$condition->setParams( [ 'theme_dir' => $themeDir ] );
		return $condition;
	}
}
