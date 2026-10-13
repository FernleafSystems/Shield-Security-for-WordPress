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
			$this->root.'/wp-content/shield/virtual-patching/ruleset.php'
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
		$this->probe->homeResults = [ new ProbeResult( true, 200 ), new ProbeResult( true, 500 ) ];

		$this->assertInstallFails( InstallException::SITE_FAILED_REVERTED, $this->installer( 'apache2handler' ) );

		$this->assertFileDoesNotExist( $this->paths->htaccess );
	}

	public function testHomePageAlreadyFailingBeforehandIsNotBlamedOnTheChange() :void {
		$this->probe->verifyResults = [ $this->verified( false ), $this->verified( true ) ];
		$this->probe->homeResults = [ new ProbeResult( true, 503 ), new ProbeResult( true, 503 ) ];

		$this->assertSame( State::INSTALLED, $this->installer( 'apache2handler' )->install()->status );
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

	public function testStubThatFailsWhenRequestedIsRemovedAndNoDirectiveIsWritten() :void {
		$this->probe->verifyResults = [ $this->verified( false ) ];
		$this->probe->smokeResult = new ProbeResult( true, 500 );

		$this->assertInstallFails( InstallException::STUB_SMOKE_FAILED, $this->installer( 'apache2handler' ) );

		$this->assertFileDoesNotExist( $this->paths->stub );
		$this->assertFileDoesNotExist( $this->paths->htaccess );
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

	private function installer(
		string $sapi,
		string $currentPrepend = '',
		string $software = 'Apache',
		string $userIni = '.user.ini',
		bool $multisite = false,
		bool $directFs = true,
		int $now = 1000
	) :Installer {
		$env = new Environment( $sapi, $userIni, 300, $currentPrepend, $software, $multisite, $directFs, true, $now );
		$paths = $this->paths;
		if ( !Mechanism::isUsableUserIniFilename( $userIni ) ) {
			$paths = new Paths( $paths->stub, $paths->stubUrl, $paths->htaccess, null, $paths->bootstrap, $paths->ruleset );
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

	public ?ProbeResult $smokeResult = null;

	public int $verifyCalls = 0;

	public function verify() :ProbeResult {
		$this->verifyCalls++;
		return \array_shift( $this->verifyResults ) ?? ProbeResult::unreachable();
	}

	public function home() :ProbeResult {
		return \array_shift( $this->homeResults ) ?? new ProbeResult( true, 200 );
	}

	public function smoke() :ProbeResult {
		return $this->smokeResult ?? new ProbeResult( true, 200, null, true );
	}
}
