<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\PluginNotices;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\MinimumRequirements;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\PhpFutureMinimum;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Utilities\Data;

class PhpFutureMinimumTest extends BaseUnitTest {

	private const NOW = 1700000000;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_url' )->alias( static fn( string $url ) :string => $url );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerCurrentRuntimeVersions' )]
	public function test_default_future_minimum_notice_is_dormant( string $phpVersion ) :void {
		$this->installEnvironment( $phpVersion );

		$this->assertNull( ( new PhpFutureMinimum() )->check() );
	}

	public static function providerCurrentRuntimeVersions() :array {
		return [
			'php 8.2' => [ '8.2.20' ],
			'php 8.3' => [ '8.3.15' ],
			'php 8.4' => [ '8.4.8' ],
		];
	}

	public function test_configured_future_minimum_returns_dismissible_info_notice_below_future_version() :void {
		$this->installEnvironment( '8.3.20' );

		$payload = ( new ConfiguredPhpFutureMinimum() )->check();

		$this->assertIsArray( $payload );
		$this->assertSame( PhpFutureMinimum::ID, $payload[ 'id' ] );
		$this->assertSame( 'info', $payload[ 'type' ] );
		$this->assertTrue( (bool)$payload[ 'can_dismiss' ] );
		$this->assertContains( 'shield_admin_top_page', $payload[ 'locations' ] );
		$this->assertStringContainsString( PhpFutureMinimum::MORE_INFO_URL, \implode( "\n", $payload[ 'text' ] ) );
	}

	public function test_configured_notice_is_hidden_while_snoozed_for_less_than_30_days() :void {
		$this->installEnvironment( '8.3.20', self::NOW - 100 );

		$this->assertNull( ( new ConfiguredPhpFutureMinimum() )->check() );
	}

	public function test_configured_notice_returns_after_30_day_snooze_expires() :void {
		$this->installEnvironment( '8.3.20', self::NOW - 30 * 86400 );

		$this->assertIsArray( ( new ConfiguredPhpFutureMinimum() )->check() );
	}

	public function test_configured_notice_waits_out_a_new_installs_first_day() :void {
		$this->installEnvironment( '8.3.20', 0, self::NOW - 3600 );
		$this->assertNull( ( new ConfiguredPhpFutureMinimum() )->check() );

		$this->installEnvironment( '8.3.20', 0, self::NOW - 2 * 86400 );
		$this->assertIsArray( ( new ConfiguredPhpFutureMinimum() )->check() );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerFutureMinimumVersions' )]
	public function test_configured_notice_is_hidden_at_or_above_future_minimum( string $phpVersion ) :void {
		$this->installEnvironment( $phpVersion );

		$this->assertNull( ( new ConfiguredPhpFutureMinimum() )->check() );
	}

	public static function providerFutureMinimumVersions() :array {
		return [
			'php 8.4' => [ '8.4.1' ],
			'php 8.5' => [ '8.5.0' ],
		];
	}

	/**
	 * @param int $installedAt when the plugin was installed; long ago unless a test is about a new install
	 */
	private function installEnvironment( string $phpVersion, int $snoozedAt = 0, int $installedAt = 1600000000 ) :PhpFutureMinimumUserMetaStub {
		$meta = new PhpFutureMinimumUserMetaStub( $snoozedAt );
		ServicesState::installItems( [
			'service_data'    => new PhpFutureMinimumDataStub( $phpVersion ),
			'service_request' => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
		] );
		PluginControllerInstaller::install(
			new PhpFutureMinimumControllerStub(
				new PhpFutureMinimumUserMetasStub( $meta ),
				$installedAt
			)
		);

		return $meta;
	}
}

class ConfiguredPhpFutureMinimum extends PhpFutureMinimum {

	protected const CURRENT_MINIMUM_PHP = MinimumRequirements::PHP;
	protected const FUTURE_MINIMUM_PHP = '8.4';
}

class PhpFutureMinimumControllerStub extends Controller {

	public function __construct( object $userMetas, int $installedAt ) {
		$this->user_metas = $userMetas;
		$this->comps = (object)[
			'opts_lookup' => new class( $installedAt ) {

				private int $installedAt;

				public function __construct( int $installedAt ) {
					$this->installedAt = $installedAt;
				}

				public function getInstalledAt() :int {
					return $this->installedAt;
				}
			},
		];
	}
}

class PhpFutureMinimumUserMetasStub {

	private PhpFutureMinimumUserMetaStub $meta;

	public function __construct( PhpFutureMinimumUserMetaStub $meta ) {
		$this->meta = $meta;
	}

	public function current() :PhpFutureMinimumUserMetaStub {
		return $this->meta;
	}
}

class PhpFutureMinimumUserMetaStub {

	private array $values = [];

	public function __construct( int $snoozedAt = 0 ) {
		if ( $snoozedAt > 0 ) {
			$this->values[ PhpFutureMinimum::SNOOZE_USER_META ] = $snoozedAt;
		}
	}

	public function __get( string $key ) {
		return $this->values[ $key ] ?? null;
	}

	public function __set( string $key, $value ) :void {
		$this->values[ $key ] = $value;
	}
}

class PhpFutureMinimumDataStub extends Data {

	private string $phpVersion;

	public function __construct( string $phpVersion ) {
		$this->phpVersion = $phpVersion;
	}

	public function getPhpVersion() :string {
		return $this->phpVersion;
	}
}
