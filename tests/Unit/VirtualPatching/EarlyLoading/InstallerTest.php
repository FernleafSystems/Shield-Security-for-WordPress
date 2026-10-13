<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\{
	Environment,
	InstallException,
	Installer,
	LoopbackProbe,
	Mechanism,
	Paths,
	ProbeResult,
	State,
	StateStore,
	StubBuilder
};
use PHPUnit\Framework\TestCase;

class InstallerTest extends TestCase {

	use TempDirLifecycleTrait;

	private const WP_RULES = "# BEGIN WordPress\nRewriteEngine On\n# END WordPress\n";

	private string $root;

	private Paths $paths;

	private InstallerTestStore $store;

	private InstallerTestProbe $probe;

	protected function setUp() :void {
		parent::setUp();
		$this->root = $this->createTrackedTempDir( 'shield-early-site-' );
		$this->paths = new Paths(
			$this->root.'/'.StubBuilder::FILE_NAME,
			'https://example.test/'.StubBuilder::FILE_NAME,
			$this->root.'/.htaccess',
			$this->root.'/.user.ini',
			$this->root.'/wp-content/plugins/wp-simple-firewall/src/VirtualPatching/EarlyLoading/bootstrap.php',
			$this->root.'/wp-content/shield/virtual-patching/ruleset.php',
			[ 'root_dirs' => [ $this->root ] ]
		);
		$this->store = new InstallerTestStore();
		$this->probe = new InstallerTestProbe();
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testModPhpInstallAddsBlockToHtaccessAndConfirmsTheStubRuns() :void {
		\file_put_contents( $this->paths->htaccess, self::WP_RULES );
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];

		$state = $this->installer( 'apache2handler' )->install();

		$this->assertSame( State::INSTALLED, $state->status );
		$this->assertTrue( $state->confirmed );
		$htaccess = (string)\file_get_contents( $this->paths->htaccess );
		$this->assertStringStartsWith( self::WP_RULES, $htaccess );
		$this->assertSame( 3, \substr_count( $htaccess, "php_value auto_prepend_file '".$this->stubPath()."'" ) );
		$this->assertFileDoesNotExist( $this->paths->userIni );
		$this->assertSame( $this->installer( 'apache2handler' )->stubContent( null ), \file_get_contents( $this->paths->stub ) );
	}

	public function testUserIniInstallIsPendingUntilPhpRereadsIt() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( false ) ];

		$state = $this->installer( 'fpm-fcgi', '', 'nginx' )->install();

		$this->assertSame( State::INSTALLED, $state->status );
		$this->assertTrue( $state->isPending( 1000 ) );
		$this->assertFalse( $state->isPending( 1000 + 300 + 60 ) );
		$this->assertSame(
			[ 'auto_prepend_file' => $this->stubPath() ],
			\parse_ini_file( $this->paths->userIni )
		);
		$this->assertFileDoesNotExist( $this->paths->htaccess );
	}

	public function testSiteFailingAfterTheChangeRestoresFilesExactlyAndKeepsTheStub() :void {
		\file_put_contents( $this->paths->htaccess, "Options -Indexes\r\n" );
		$this->probe->verifyResults = [ $this->verified( false ), new ProbeResult( true, 500 ) ];

		$this->assertInstallFails( InstallException::SITE_FAILED_REVERTED, $this->installer( 'litespeed', '', 'LiteSpeed' ) );

		$this->assertSame( "Options -Indexes\r\n", \file_get_contents( $this->paths->htaccess ) );
		$this->assertFileDoesNotExist( $this->paths->userIni );
		$this->assertFileExists( $this->paths->stub );
		$this->assertSame( State::REMOVING, $this->store->state->status );
		$this->assertSame( InstallException::SITE_FAILED_REVERTED, $this->store->state->error );
	}

	public function testHomePageBreakingAfterTheChangeIsReverted() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->probe->homeResults = [ $this->verified( false ), new ProbeResult( true, 500 ) ];

		$this->assertInstallFails( InstallException::SITE_FAILED_REVERTED, $this->installer( 'apache2handler' ) );

		$this->assertFileDoesNotExist( $this->paths->htaccess );
	}

	public function testDisplayedFatalErrorWithStatus200IsReverted() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->probe->homeResults = [ $this->verified( false ), new ProbeResult( true, 200 ) ];

		$this->assertInstallFails( InstallException::SITE_FAILED_REVERTED, $this->installer( 'apache2handler' ) );

		$this->assertFileDoesNotExist( $this->paths->htaccess );
	}

	public function testHomePageThatCannotBeVerifiedBeforehandBlocksTheChange() :void {
		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->probe->homeResults = [ new ProbeResult( true, 503 ) ];

		$this->assertInstallFails( InstallException::LOOPBACK_UNAVAILABLE, $this->installer( 'apache2handler' ) );

		$this->assertFileDoesNotExist( $this->paths->stub );
		$this->assertFileDoesNotExist( $this->paths->htaccess );
	}

	public function testAnotherPrependInUserIniBlocksInstallWithoutWritingAnything() :void {
		$original = "; BEGIN AP Patchstack\nauto_prepend_file = '/x/mu-plugin-ap.php'\n; END AP Patchstack\n";
		\file_put_contents( $this->paths->userIni, $original );

		$e = $this->assertInstallFails( InstallException::CONFLICT, $this->installer( 'fpm-fcgi', '', 'nginx' ) );

		$this->assertSame( $this->paths->userIni, $e->configFile() );
		$this->assertSame( $original, \file_get_contents( $this->paths->userIni ) );
		$this->assertFileDoesNotExist( $this->paths->stub );
		$this->assertSame( 0, $this->probe->verifyCalls );
		$this->assertSame( InstallException::CONFLICT, $this->store->state->error );
	}

	public function testHtaccessPrependIsNotAConflictWhenPhpFpmIgnoresIt() :void {
		\file_put_contents( $this->paths->htaccess, "<IfModule mod_php.c>\nphp_value auto_prepend_file '/old.php'\n</IfModule>\n" );
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( false ) ];

		$this->installer( 'fpm-fcgi', '', 'Apache/2.4' )->install();

		$this->assertStringContainsString( '<Files ".user.ini">', (string)\file_get_contents( $this->paths->htaccess ) );
	}

	public function testUnavailableLoopbackRefusesBeforeWritingAnything() :void {
		$this->probe->verifyResults = [ ProbeResult::unreachable() ];

		$this->assertInstallFails( InstallException::LOOPBACK_UNAVAILABLE, $this->installer( 'apache2handler' ) );

		$this->assertFileDoesNotExist( $this->paths->stub );
		$this->assertFileDoesNotExist( $this->paths->htaccess );
	}

	public function testStubThatFailsWhenRequestedIsNeverWrittenAndNoDirectiveIsWritten() :void {
		foreach ( [ 'server error' => new ProbeResult( true, 500 ), 'displayed fatal error' => new ProbeResult( true, 200 ) ] as $case => $smoke ) {
			$this->probe->verifyResults = [ $this->verified( false ) ];
			$this->probe->smokeResults = [ $smoke ];

			$this->assertInstallFails( InstallException::STUB_SMOKE_FAILED, $this->installer( 'apache2handler' ) );

			$this->assertFileDoesNotExist( $this->paths->stub, $case );
			$this->assertFileDoesNotExist( $this->paths->htaccess, $case );
			$this->assertSame( [], $this->candidates(), $case );
		}
	}

	public function testFailingNewStubLeavesTheStubInUseAlone() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler' )->install();
		$working = (string)\file_get_contents( $this->paths->stub );

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$this->probe->smokeResults = [ new ProbeResult( true, 500 ) ];
		$this->assertInstallFails( InstallException::STUB_SMOKE_FAILED, $this->installer( 'apache2handler', '/opt/host/prepend.php' ) );

		$this->assertSame( $working, \file_get_contents( $this->paths->stub ) );
		$this->assertSame( [], $this->candidates() );
	}

	public function testStubCheckThatCannotBeMadeDoesNotStopTheInstall() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->probe->smokeResults = [ new ProbeResult( true, 403 ) ];

		$this->assertSame( State::INSTALLED, $this->installer( 'apache2handler' )->install()->status );
		$this->assertStringEndsWith( '.php', $this->probe->smokeUrls[ 0 ] );
		$this->assertStringStartsWith( 'https://example.test/shield-early-loader-check-', $this->probe->smokeUrls[ 0 ] );
	}

	public function testHostPrependIsChainedFromTheStub() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];

		$installer = $this->installer( 'apache2handler', '/opt/host/prepend.php' );
		$state = $installer->install();

		$this->assertSame( '/opt/host/prepend.php', $state->hostPrepend );
		$this->assertSame( $installer->stubContent( '/opt/host/prepend.php' ), \file_get_contents( $this->paths->stub ) );
	}

	public function testReinstallWhileTheStubIsActiveKeepsTheRecordedHostPrepend() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler', '/opt/host/prepend.php' )->install();

		$this->probe->verifyResults = [ $this->verified( true ), $this->verified( true ) ];
		$state = $this->installer( 'apache2handler', $this->paths->stub )->install();

		$this->assertSame( '/opt/host/prepend.php', $state->hostPrepend );
	}

	public function testRelativeHostPrependIsRefused() :void {
		$this->assertInstallFails( InstallException::HOST_PREPEND_NOT_ABSOLUTE, $this->installer( 'apache2handler', 'prepend.php' ) );
	}

	public function testManualMechanismOnlyWritesTheStub() :void {
		$state = $this->installer( 'cgi-fcgi', '', 'nginx', '' )->install();

		$this->assertSame( State::MANUAL, $state->status );
		$this->assertFileExists( $this->paths->stub );
		$this->assertFileDoesNotExist( $this->paths->htaccess );
		$this->assertSame( 0, $this->probe->verifyCalls );
	}

	public function testMultisiteAndIndirectFilesystemsAreRefused() :void {
		$this->assertInstallFails( InstallException::MULTISITE, $this->installer( 'apache2handler', '', 'Apache', '.user.ini', true ) );
		$this->assertInstallFails(
			InstallException::FILESYSTEM_NOT_DIRECT,
			$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, false )
		);
		$this->assertFileDoesNotExist( $this->paths->stub );
	}

	public function testRemovalKeepsTheStubUntilPhpCacheHasExpired() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'fpm-fcgi', '', 'nginx' )->install();

		$state = $this->installer( 'fpm-fcgi', '', 'nginx', '.user.ini', false, true, 2000 )->removeDirectives();
		$this->assertSame( State::REMOVING, $state->status );
		$this->assertSame( '', \trim( (string)\file_get_contents( $this->paths->userIni ) ) );

		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->installer( 'fpm-fcgi', '', 'nginx', '.user.ini', false, true, 2000 + 300 )->finishRemoval();
		$this->assertFileExists( $this->paths->stub, 'One worker no longer loading it does not prove they all stopped.' );

		$this->probe->verifyResults = [ $this->verified( false ) ];
		$state = $this->installer( 'fpm-fcgi', '', 'nginx', '.user.ini', false, true, 2000 + 360 )->finishRemoval();
		$this->assertFileDoesNotExist( $this->paths->stub );
		$this->assertSame( State::OFF, $state->status );
	}

	public function testStubIsKeptWhileItStillRuns() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler' )->install();
		$this->installer( 'apache2handler' )->removeDirectives();

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 5000 )->finishRemoval();
		$this->assertFileExists( $this->paths->stub );

		$this->installer( 'apache2handler', $this->paths->stub, 'Apache', '.user.ini', false, true, 5000 )->finishRemoval();
		$this->assertFileExists( $this->paths->stub, 'This request itself prepended the stub.' );

		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 5000 )->finishRemoval();
		$this->assertFileDoesNotExist( $this->paths->stub );
	}

	public function testManualStubIsOnlyRemovedOnceVerifiedNotRunning() :void {
		$this->installer( 'cgi-fcgi', '', 'nginx', '' )->install();
		$this->installer( 'cgi-fcgi', '', 'nginx', '' )->removeDirectives();

		$this->probe->verifyResults = [ ProbeResult::unreachable() ];
		$this->installer( 'cgi-fcgi', '', 'nginx', '', false, true, 99999 )->finishRemoval();
		$this->assertFileExists( $this->paths->stub );

		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->installer( 'cgi-fcgi', '', 'nginx', '', false, true, 99999 )->finishRemoval();
		$this->assertFileDoesNotExist( $this->paths->stub );
	}

	public function testMovingToPhpFpmRemovesTheOldHtaccessBlock() :void {
		\file_put_contents( $this->paths->htaccess, self::WP_RULES );
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler' )->install();

		$this->probe->verifyResults = [ $this->verified( true ), $this->verified( true ) ];
		$this->installer( 'fpm-fcgi', $this->paths->stub, 'nginx' )->install();

		$this->assertSame( self::WP_RULES, \rtrim( (string)\file_get_contents( $this->paths->htaccess ), "\n" )."\n" );
		$this->assertFileExists( $this->paths->userIni );
	}

	public function testRefreshRewritesAnOutdatedStubAndRecordsWhetherItRuns() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( false ) ];
		$this->installer( 'fpm-fcgi', '', 'nginx' )->install();
		\file_put_contents( $this->paths->stub, '<?php // outdated' );

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$installer = $this->installer( 'fpm-fcgi', '', 'nginx' );
		$state = $installer->refresh();

		$this->assertTrue( $state->confirmed );
		$this->assertSame( $installer->stubContent( null ), \file_get_contents( $this->paths->stub ) );
	}

	public function testRollbackKeepsChangesOthersMadeMeanwhile() :void {
		\file_put_contents( $this->paths->htaccess, self::WP_RULES );
		$this->probe->verifyResults = [ $this->verified( false ), new ProbeResult( true, 500 ) ];
		$this->probe->onVerify = function ( int $call ) {
			if ( $call === 2 ) {
				// WordPress saves its rewrite rules while Shield is checking the site.
				\file_put_contents( $this->paths->htaccess, "# added by WordPress\n", \FILE_APPEND );
			}
		};

		$this->assertInstallFails( InstallException::SITE_FAILED_REVERTED, $this->installer( 'apache2handler' ) );

		$htaccess = (string)\file_get_contents( $this->paths->htaccess );
		$this->assertStringContainsString( '# added by WordPress', $htaccess );
		$this->assertStringNotContainsString( 'Shield Early Loading', $htaccess );
		$this->assertStringStartsWith( self::WP_RULES, $htaccess );
	}

	public function testInterruptedInstallIsPutBackLater() :void {
		\file_put_contents( $this->paths->htaccess, self::WP_RULES );
		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->probe->onVerify = function ( int $call ) {
			if ( $call === 2 ) {
				throw new \RuntimeException( 'PHP stopped the request' );
			}
		};
		try {
			$this->installer( 'apache2handler' )->install();
			$this->fail( 'Expected the request to stop.' );
		}
		catch ( \RuntimeException $e ) {
		}
		$this->probe->onVerify = null;
		$this->assertSame( State::INSTALLING, $this->store->state->status );
		$this->assertStringContainsString( 'Shield Early Loading', (string)\file_get_contents( $this->paths->htaccess ) );

		$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 1000 + 60 )->recoverInterrupted();
		$this->assertSame( State::INSTALLING, $this->store->state->status, 'Too early to be sure it was interrupted.' );

		$state = $this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 1000 + Installer::INTERRUPTED_AFTER )->recoverInterrupted();
		$this->assertSame( State::REMOVING, $state->status );
		$this->assertSame( InstallException::INSTALL_INTERRUPTED, $state->error );
		$this->assertSame( self::WP_RULES, \file_get_contents( $this->paths->htaccess ) );
	}

	public function testCachedInstallIsUndoneWhenTheSiteFailsOnceTheCacheExpires() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( false ) ];
		$this->installer( 'fpm-fcgi', '', 'nginx' )->install();
		$this->assertFileExists( $this->paths->userIni );

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$this->probe->homeResults = [ new ProbeResult( true, 500 ) ];
		$state = $this->installer( 'fpm-fcgi', '', 'nginx', '.user.ini', false, true, 1000 + 360 )->refresh();

		$this->assertSame( State::REMOVING, $state->status );
		$this->assertSame( InstallException::SITE_FAILED_REVERTED, $state->error );
		$this->assertFileDoesNotExist( $this->paths->userIni );
		$this->assertFileExists( $this->paths->stub );
	}

	public function testCachedInstallSettlesOnlyOnAConclusiveCheck() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( false ) ];
		$this->installer( 'fpm-fcgi', '', 'nginx' )->install();

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$this->probe->homeResults = [ ProbeResult::unreachable() ];
		$state = $this->installer( 'fpm-fcgi', '', 'nginx', '.user.ini', false, true, 1000 + 360 )->refresh();
		$this->assertSame( State::INSTALLED, $state->status );
		$this->assertFalse( $state->settled, 'A failed request proves nothing.' );

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$state = $this->installer( 'fpm-fcgi', '', 'nginx', '.user.ini', false, true, 1000 + 400 )->refresh();
		$this->assertTrue( $state->settled );
		$this->assertSame( [], $state->edits );
	}

	public function testSuccessfulRefreshClearsAnEarlierError() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler' )->install();
		$this->store->state->error = InstallException::STUB_WRITE_FAILED;

		$this->probe->verifyResults = [ $this->verified( true ) ];
		$this->assertSame( '', $this->installer( 'apache2handler' )->refresh()->error );
	}

	public function testStubIsOnlyRemovedFromAWebRequestThatConfirmsItStopped() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler' )->install();
		$this->installer( 'apache2handler' )->removeDirectives();

		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 5000, false )->finishRemoval();
		$this->assertFileExists( $this->paths->stub, 'WP-CLI does not see the web server configuration.' );

		$this->probe->verifyResults = [ ProbeResult::unreachable() ];
		$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 5000 )->finishRemoval();
		$this->assertFileExists( $this->paths->stub, 'A failed loopback proves nothing.' );

		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->installer( 'apache2handler', '', 'Apache', '.user.ini', false, true, 5000 )->finishRemoval();
		$this->assertFileDoesNotExist( $this->paths->stub );
	}

	public function testRemovalFindsTheFilesRecordedAtInstall() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->installer( 'apache2handler' )->install();

		// A request that works the paths out differently, such as WP-CLI with WordPress in its own directory.
		$elsewhere = $this->createTrackedTempDir( 'shield-early-elsewhere-' );
		$paths = new Paths( $this->paths->stub, $this->paths->stubUrl, $elsewhere.'/.htaccess', $elsewhere.'/.user.ini',
			$this->paths->bootstrap, $this->paths->ruleset, $this->paths->scope );
		$env = new Environment( 'cli', '.user.ini', 300, '', '', false, true, false, 2000 );
		( new Installer( $paths, $env, $this->probe, $this->store ) )->removeDirectives();

		$this->assertStringNotContainsString( 'Shield Early Loading', (string)\file_get_contents( $this->paths->htaccess ) );
	}
	private function installer(
		string $sapi,
		string $currentPrepend = '',
		string $software = 'Apache',
		string $userIni = '.user.ini',
		bool $multisite = false,
		bool $directFs = true,
		int $now = 1000,
		bool $web = true
	) :Installer {
		$env = new Environment( $sapi, $userIni, 300, $currentPrepend, $software, $multisite, $directFs, $web, $now );
		$paths = $this->paths;
		if ( !Mechanism::isUsableUserIniFilename( $userIni ) ) {
			$paths = new Paths( $paths->stub, $paths->stubUrl, $paths->htaccess, null, $paths->bootstrap, $paths->ruleset, $paths->scope );
		}
		return new Installer( $paths, $env, $this->probe, $this->store );
	}

	private function assertInstallFails( string $reason, Installer $installer ) :InstallException {
		try {
			$installer->install();
		}
		catch ( InstallException $e ) {
			$this->assertSame( $reason, $e->reason() );
			return $e;
		}
		$this->fail( 'Expected install to fail with '.$reason );
	}

	private function verified( bool $loaded ) :ProbeResult {
		return new ProbeResult( true, 200, $loaded );
	}

	/**
	 * @return string[]
	 */
	private function candidates() :array {
		return \glob( $this->root.'/shield-early-loader-check-*.php' ) ?: [];
	}

	private function stubPath() :string {
		return \str_replace( '\\', '/', $this->paths->stub );
	}
}

class InstallerTestStore implements StateStore {

	public State $state;

	public function __construct() {
		$this->state = new State();
	}

	public function load() :State {
		return State::fromArray( $this->state->toArray() );
	}

	public function save( State $state ) :void {
		$this->state = State::fromArray( $state->toArray() );
	}
}

class InstallerTestProbe implements LoopbackProbe {

	/**
	 * @var ProbeResult[]
	 */
	public array $verifyResults = [];

	/**
	 * @var ProbeResult[]
	 */
	public array $homeResults = [];

	/**
	 * @var ProbeResult[]
	 */
	public array $smokeResults = [];

	/**
	 * @var string[]
	 */
	public array $smokeUrls = [];

	/**
	 * @var ?callable
	 */
	public $onVerify = null;

	public int $verifyCalls = 0;

	public function verify() :ProbeResult {
		$this->verifyCalls++;
		if ( $this->onVerify !== null ) {
			( $this->onVerify )( $this->verifyCalls );
		}
		return \array_shift( $this->verifyResults ) ?? ProbeResult::unreachable();
	}

	public function home() :ProbeResult {
		return \array_shift( $this->homeResults ) ?? new ProbeResult( true, 200, false );
	}

	public function prepare() :void {
	}

	public function smoke( string $url ) :ProbeResult {
		$this->smokeUrls[] = $url;
		return \array_shift( $this->smokeResults ) ?? new ProbeResult( true, 200, null, true );
	}
}
