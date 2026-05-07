<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\WordpressReleaseChannel;
use FernleafSystems\Wordpress\Services\Core\General;

class WordpressReleaseChannelTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_is_development_build_detects_stable_and_prerelease_versions() :void {
		$this->assertFalse( $this->newReleaseChannelForVersion( '6.8.1' )->isDevelopmentBuild() );
		$this->assertTrue( $this->newReleaseChannelForVersion( '6.9-beta1' )->isDevelopmentBuild() );
		$this->assertTrue( $this->newReleaseChannelForVersion( '6.9-RC2' )->isDevelopmentBuild() );
		$this->assertTrue( $this->newReleaseChannelForVersion( '7.0-beta6-src' )->isDevelopmentBuild() );
	}

	private function newReleaseChannelForVersion( string $version ) :WordpressReleaseChannel {
		ServicesState::installItems( [
			'service_wpgeneral' => new class( $version ) extends General {
				private string $version;

				public function __construct( string $version ) {
					$this->version = $version;
				}

				public function getVersion( $ignoreClassicpress = false ) {
					return $this->version;
				}
			},
		] );

		return new WordpressReleaseChannel();
	}
}
