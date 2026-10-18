<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\BlockLog;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\EarlyPass;
use PHPUnit\Framework\TestCase;

class EarlyPassTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $events;

	protected function setUp() :void {
		parent::setUp();
		$this->events = $this->createTrackedTempDir( 'shield-early-pass-' ).'/events.php';
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testMatchingEarlyRuleBlocksAndIsLogged() :void {
		$pass = new EarlyPass( $this->ruleset( [ $this->installerRule() ] ), $this->server( 'POST', '/wp-content/plugins/demo/install.php' ), [], [ 'step' => '2' ] );

		$blocked = $pass->run();

		$this->assertSame( 'demo-installer', $blocked[ 'id' ] ?? null );
		$entries = BlockLog::read( $this->events );
		$this->assertCount( 1, $entries );
		$this->assertSame( [ 'demo-installer', 'early', 'match', '203.0.113.9' ], [
			$entries[ 0 ][ 'rule' ], $entries[ 0 ][ 'pass' ], $entries[ 0 ][ 'verdict' ], $entries[ 0 ][ 'ip' ],
		] );
	}

	public function testNormalRequestPassesAndEarlyRulesCountAsDecided() :void {
		$pass = new EarlyPass( $this->ruleset( [ $this->installerRule() ] ), $this->server( 'GET', '/wp-content/plugins/demo/install.php' ), [], [] );

		$this->assertNull( $pass->run() );
		$this->assertTrue( $pass->isComplete() );
		$this->assertSame( [], BlockLog::read( $this->events ) );
	}

	public function testHeaderRulesAreDecidedBeforeWordPress() :void {
		$rule = [
			'id'         => 'header',
			'advisories' => [],
			'conditions' => [
				[ 'kind' => 'header_value', 'negate' => false, 'name' => 'X-Forwarded-For', 'operator' => 'contains', 'value' => '<' ],
			],
		];
		$server = $this->server( 'GET', '/' ) + [ 'HTTP_X_FORWARDED_FOR' => '198.51.100.23<script>' ];

		$this->assertSame( 'header', ( new EarlyPass( $this->ruleset( [ $rule ] ), $server, [], [] ) )->run()[ 'id' ] ?? null );
	}

	public function testRulesNeedingWordPressAreLeftAlone() :void {
		$rule = [
			'id'         => 'needs-user',
			'advisories' => [],
			'conditions' => [
				[ 'kind' => 'path', 'negate' => false, 'operator' => 'contains', 'value' => '/demo/' ],
				[ 'kind' => 'user_capability', 'negate' => true, 'capability' => 'manage_options' ],
			],
		];
		$pass = new EarlyPass( $this->ruleset( [ $rule ] ), $this->server( 'GET', '/wp-content/plugins/demo/x.php' ), [], [] );

		$this->assertNull( $pass->run() );
		$this->assertTrue( $pass->isComplete() );
	}

	public function testRulesThatReadRestValuesAreLeftToWordPress() :void {
		$rule = [
			'id'         => 'markup',
			'advisories' => [],
			'conditions' => [
				[ 'kind' => 'or', 'conditions' => [
					[ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'form', 'name' => 'post_content', 'operator' => 'contains', 'value' => 'onfocus' ],
					[ 'kind' => 'rest_parameter_value', 'negate' => false, 'name' => 'content', 'operator' => 'contains', 'value' => 'onfocus' ],
				] ],
			],
		];
		$pass = new EarlyPass( $this->ruleset( [ $rule ] ), $this->server( 'POST', '/wp-admin/post.php' ), [], [ 'post_content' => 'x" onfocus="y' ] );

		$this->assertNull( $pass->run() );
		$this->assertTrue( $pass->isComplete() );
		$this->assertSame( [], BlockLog::read( $this->events ) );
	}

	public function testBypassedIpIsNeverBlocked() :void {
		$ruleset = $this->ruleset( [ $this->installerRule() ] );
		$ruleset[ 'bypass' ] = [ 'ip_source' => 'REMOTE_ADDR', 'ips' => [ '203.0.113.0/24' ] ];
		$pass = new EarlyPass( $ruleset, $this->server( 'POST', '/wp-content/plugins/demo/install.php' ), [], [ 'step' => '2' ] );

		$this->assertNull( $pass->run() );
		$this->assertTrue( $pass->isComplete() );
	}

	public function testRuleThatCannotBeEvaluatedLeavesThePassIncomplete() :void {
		$broken = [ 'id' => 'broken', 'advisories' => [], 'conditions' => [ [ 'kind' => 'not_a_condition', 'negate' => false ] ] ];
		$pass = new EarlyPass( $this->ruleset( [ $broken ] ), $this->server( 'GET', '/' ), [], [] );

		$this->assertNull( $pass->run() );
		$this->assertFalse( $pass->isComplete() );
	}

	public function testOnlyThisSitesWordPressFilesAreCovered() :void {
		$home = $this->createTrackedTempDir( 'shield-early-home-' );
		$abspath = $home.'/wp';
		$content = $this->createTrackedTempDir( 'shield-early-content-' );
		foreach ( [
			$home.'/index.php',
			$home.'/other-app/install.php',
			$abspath.'/wp-load.php',
			$abspath.'/wp-login.php',
			$abspath.'/wp-admin/admin-ajax.php',
			$abspath.'/forum/index.php',
			$abspath.'/wp-content/plugins/demo/install.php',
			$abspath.'/wp-content/shop/wp-load.php',
			$abspath.'/wp-content/shop/wp-content/plugins/demo/install.php',
			$content.'/plugins/demo/install.php',
		] as $file ) {
			if ( !\is_dir( \dirname( $file ) ) ) {
				\mkdir( \dirname( $file ), 0777, true );
			}
			\file_put_contents( $file, '<?php' );
		}
		$scope = [
			'files'     => [ $home.'/index.php' ],
			'root_dirs' => [ $abspath ],
			'dirs'      => [ $abspath.'/wp-admin', $abspath.'/wp-content', $content ],
		];

		$this->assertTrue( EarlyPass::coversScript( $home.'/index.php', $scope ), 'Front controller when WordPress has its own directory' );
		$this->assertTrue( EarlyPass::coversScript( $abspath.'/wp-login.php', $scope ) );
		$this->assertTrue( EarlyPass::coversScript( $abspath.'/wp-admin/admin-ajax.php', $scope ) );
		$this->assertTrue( EarlyPass::coversScript( $abspath.'/wp-content/plugins/demo/install.php', $scope ) );
		$this->assertTrue( EarlyPass::coversScript( $content.'/plugins/demo/install.php', $scope ), 'Content directory outside ABSPATH' );
		$this->assertFalse( EarlyPass::coversScript( $abspath.'/forum/index.php', $scope ), 'Another application in a folder' );
		$this->assertFalse( EarlyPass::coversScript( $home.'/other-app/install.php', $scope ), 'Another application beside WordPress' );
		$this->assertFalse( EarlyPass::coversScript( $abspath.'/wp-content/shop/wp-content/plugins/demo/install.php', $scope ), 'Another WordPress site in a folder' );
		$this->assertFalse( EarlyPass::coversScript( $abspath.'/missing.php', $scope ) );
		$this->assertFalse( EarlyPass::coversScript( '', $scope ) );
	}
	private function installerRule() :array {
		return [
			'id'         => 'demo-installer',
			'advisories' => [ 'CVE-2026-0001' ],
			'conditions' => [
				[ 'kind' => 'method', 'negate' => false, 'value' => 'POST' ],
				[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-content/plugins/demo/install.php' ],
				[ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'form', 'name' => 'step' ],
			],
		];
	}

	private function ruleset( array $rules ) :array {
		return [
			'format'         => 1,
			'hash'           => 'test',
			'active_plugins' => [],
			'active_themes'  => [],
			'rules'          => $rules,
			'bypass'         => [ 'ip_source' => 'REMOTE_ADDR', 'ips' => [] ],
			'events'         => $this->events,
		];
	}

	private function server( string $method, string $uri ) :array {
		return [
			'REQUEST_METHOD'  => $method,
			'REQUEST_URI'     => $uri,
			'SCRIPT_NAME'     => \parse_url( $uri, \PHP_URL_PATH ),
			'SCRIPT_FILENAME' => '/var/www/html'.\parse_url( $uri, \PHP_URL_PATH ),
			'REMOTE_ADDR'     => '203.0.113.9',
		];
	}
}
