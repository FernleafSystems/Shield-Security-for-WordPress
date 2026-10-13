<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\BlockLog;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\StubBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile;
use PHPUnit\Framework\TestCase;

/**
 * Serves a PHP file that never loads WordPress through PHP's built-in web server, with the generated stub as
 * auto_prepend_file and the real bootstrap and ruleset, to show the chain blocks the affected request only.
 */
class EarlyLoaderServerTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $root;

	private string $events;

	private string $rulesetPath;

	/**
	 * @var resource|null
	 */
	private $server = null;

	private int $port = 0;

	protected function setUp() :void {
		parent::setUp();
		$this->root = $this->createTrackedTempDir( 'shield-early-server-' );
		$cache = $this->createTrackedTempDir( 'shield-early-cache-' );
		$this->events = $cache.'/events.php';
		$this->rulesetPath = $cache.'/ruleset.php';

		$script = '<?php echo "reached|", defined("SHIELD_EARLY_RAN") ? SHIELD_EARLY_RAN : "not-ran";';
		\mkdir( $this->root.'/wp-content/plugins/demo', 0777, true );
		\file_put_contents( $this->root.'/wp-content/plugins/demo/install.php', $script );
		// Another WordPress site installed in a folder below this one.
		\mkdir( $this->root.'/blog/wp-content/plugins/demo', 0777, true );
		\file_put_contents( $this->root.'/blog/wp-load.php', '<?php' );
		\file_put_contents( $this->root.'/blog/wp-content/plugins/demo/install.php', $script );
		// Another application in the same hosting account.
		\mkdir( $this->root.'/other-app', 0777, true );
		\file_put_contents( $this->root.'/other-app/install.php', $script );

		\file_put_contents( $this->rulesetPath, RulesetFile::render( [
			'format'         => RulesetFile::FORMAT,
			'generated_at'   => 1,
			'active_plugins' => [ 'demo/demo.php' ],
			'active_themes'  => [],
			'rules'          => [
				[
					'id'         => 'demo-installer',
					'advisories' => [ 'CVE-2026-0001' ],
					'conditions' => [
						[ 'kind' => 'method', 'negate' => false, 'value' => 'POST' ],
						[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-content/plugins/demo/install.php' ],
						[ 'kind' => 'plugin_active', 'negate' => false, 'pluginFile' => 'demo/demo.php' ],
					],
				],
				[
					'id'         => 'any-install-script',
					'advisories' => [ 'CVE-2026-0002' ],
					'conditions' => [
						[ 'kind' => 'method', 'negate' => false, 'value' => 'POST' ],
						[ 'kind' => 'script', 'negate' => false, 'operator' => 'equals', 'value' => 'install.php' ],
					],
				],
			],
			'bypass'         => [ 'ip_source' => 'REMOTE_ADDR', 'ips' => [] ],
			'events'         => $this->events,
		] ) );

		$bootstrap = \dirname( __DIR__, 4 ).'/src/VirtualPatching/EarlyLoading/bootstrap.php';
		\file_put_contents( $this->root.'/'.StubBuilder::FILE_NAME, StubBuilder::build( $bootstrap, $this->rulesetPath, null, [
			'root_dirs' => [ $this->root ],
			'dirs'      => [ $this->root.'/wp-content' ],
		] ) );

		$this->startServer();
	}

	protected function tearDown() :void {
		if ( \is_resource( $this->server ) ) {
			\proc_terminate( $this->server );
			\proc_close( $this->server );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testAffectedRequestIsBlockedBeforeTheScriptRuns() :void {
		[ $status, $body ] = $this->request( 'POST', '/wp-content/plugins/demo/install.php', 'step=2' );

		$this->assertSame( 403, $status );
		$this->assertSame( 'Forbidden', $body );
		$entries = BlockLog::read( $this->events );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'early', $entries[ 0 ][ 'pass' ] );
	}

	public function testNormalRequestReachesTheScriptWithTheSkipSignal() :void {
		[ $status, $body ] = $this->request( 'GET', '/wp-content/plugins/demo/install.php' );

		$this->assertSame( 200, $status );
		$this->assertSame( 'reached|'.RulesetFile::read( $this->rulesetPath )[ 'hash' ], $body );
		$this->assertFileDoesNotExist( $this->events );
	}

	public function testEncodedPathCannotAvoidTheRule() :void {
		[ $status ] = $this->request( 'POST', '/wp-content/plugins/demo/%69nstall.php', 'step=2' );

		$this->assertSame( 403, $status );
	}

	public function testWordPressInAFolderBelowIsAnotherSiteAndIsNotChecked() :void {
		[ $status, $body ] = $this->request( 'POST', '/blog/wp-content/plugins/demo/install.php', 'step=2' );

		$this->assertSame( 200, $status );
		$this->assertSame( 'reached|not-ran', $body );
	}

	public function testAnotherApplicationIsNotChecked() :void {
		[ $status, $body ] = $this->request( 'POST', '/other-app/install.php', 'step=2' );

		$this->assertSame( 200, $status );
		$this->assertSame( 'reached|not-ran', $body );
	}

	public function testDamagedRulesetLeavesRequestsToWordPress() :void {
		\file_put_contents( $this->rulesetPath, '<?php return ["format" => 1, "rules" => [], "hash" => "wrong"];' );

		[ $status, $body ] = $this->request( 'POST', '/wp-content/plugins/demo/install.php', 'step=2' );

		$this->assertSame( 200, $status );
		$this->assertSame( 'reached|not-ran', $body );
	}

	private function startServer() :void {
		$socket = \stream_socket_server( 'tcp://127.0.0.1:0' );
		$this->assertNotFalse( $socket );
		$this->port = (int)\substr( (string)\strrchr( (string)\stream_socket_get_name( $socket, false ), ':' ), 1 );
		\fclose( $socket );

		$log = $this->createTrackedTempDir( 'shield-early-server-log-' ).'/server.log';
		$this->server = \proc_open( [
			\PHP_BINARY, '-n',
			'-d', 'display_errors=0',
			'-d', 'auto_prepend_file='.$this->root.'/'.StubBuilder::FILE_NAME,
			'-S', '127.0.0.1:'.$this->port,
			'-t', $this->root,
		], [ 1 => [ 'file', $log, 'a' ], 2 => [ 'file', $log, 'a' ] ], $pipes );
		$this->assertIsResource( $this->server );

		$deadline = \microtime( true ) + 10;
		while ( \microtime( true ) < $deadline ) {
			$connection = @\fsockopen( '127.0.0.1', $this->port, $errno, $errstr, 0.2 );
			if ( $connection !== false ) {
				\fclose( $connection );
				return;
			}
			\usleep( 100000 );
		}
		$this->fail( 'PHP built-in server did not start.' );
	}

	/**
	 * @return array{0:int,1:string}
	 */
	private function request( string $method, string $path, string $body = '' ) :array {
		$socket = \stream_socket_client( 'tcp://127.0.0.1:'.$this->port, $errno, $errstr, 10 );
		$this->assertIsResource( $socket );
		\fwrite( $socket, \implode( "\r\n", [
			sprintf( '%s %s HTTP/1.0', $method, $path ),
			'Host: 127.0.0.1',
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.\strlen( $body ),
			'',
			$body,
		] ) );
		$response = (string)\stream_get_contents( $socket );
		\fclose( $socket );

		[ $head, $content ] = \array_pad( \explode( "\r\n\r\n", $response, 2 ), 2, '' );
		\preg_match( '#^HTTP/\S+\s+(\d{3})#', $head, $match );
		return [ (int)( $match[ 1 ] ?? 0 ), $content ];
	}
}
