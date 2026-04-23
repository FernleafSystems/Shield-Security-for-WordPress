<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

if ( !\function_exists( __NAMESPACE__.'\\path_join' ) ) {
	function path_join( string $base, string $path ) :string {
		return \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' );
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class ModConSiteLoopbackTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();

		if ( \class_exists( '\WP_Site_Health', false ) ) {
			$this->markTestSkipped( 'Unavailable Site Health test requires WP_Site_Health to be absent.' );
		}

		ServicesState::installItems( [
			'service_wpfs' => new class extends Fs {
				public function isAccessibleFile( string $path ) :bool {
					unset( $path );
					return false;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testSiteHealthUnavailableReturnsFalseWithoutFallbackProbe() :void {
		Functions\expect( 'wp_remote_post' )->never();

		$this->assertFalse( ( new ModCon() )->canSiteLoopback() );
	}
}
