<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\AuditTrail\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Text\SafeDisplayText;

class ActivityLogMessageBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testBuildPlainAppliesSafeDisplayTextToMetaSubstitutions() :void {
		$plain = ActivityLogMessageBuilder::BuildPlain( 'malicious_event', [
			'actor'       => "admin\n<script>alert(1)</script>",
			'target'      => [
				'file' => "wp-config.php\r\nowned",
			],
			'audit_count' => 3,
		] );

		$this->assertStringContainsString( 'Actor admin <script>alert(1)</script>', $plain );
		$this->assertStringContainsString( 'target {"file":"wp-config.php\\r\\nowned"}', $plain );
		$this->assertStringContainsString( 'This event repeated 3 times in the last 24hrs.', $plain );
		$this->assertStringNotContainsString( "admin\n<script>", $plain );
	}

	public function testBuildHtmlLinesEscapesSafeDisplayTextOutput() :void {
		$record = new LogRecord();
		$record->event_slug = 'malicious_event';
		$record->meta_data = [
			'actor'       => '<img src=x onerror=alert(1)>',
			'target'      => 'settings<script>alert(2)</script>',
			'audit_count' => 1,
		];

		$lines = ActivityLogMessageBuilder::BuildHtmlLinesFromLogRecord( $record );

		$this->assertSame(
			'Actor &lt;img src=x onerror=alert(1)&gt; touched target settings&lt;script&gt;alert(2)&lt;/script&gt;',
			$lines[ 0 ]
		);
		$this->assertStringNotContainsString( '<img', \implode( "\n", $lines ) );
		$this->assertStringNotContainsString( '<script', \implode( "\n", $lines ) );
	}

	public function testBuildPlainTruncatesOversizedMetaSubstitutions() :void {
		$plain = ActivityLogMessageBuilder::BuildPlain( 'malicious_event', [
			'actor'  => \str_repeat( 'A', SafeDisplayText::DEFAULT_MAX_BYTES + 10 ),
			'target' => 'settings',
		] );

		$this->assertStringContainsString( SafeDisplayText::TRUNCATION_SUFFIX, $plain );
		$this->assertLessThan(
			SafeDisplayText::DEFAULT_MAX_BYTES + 80,
			\strlen( $plain )
		);
	}

	public function testCompatibilityWrappersUseSafePlainPathway() :void {
		$record = new LogRecord();
		$record->event_slug = 'malicious_event';
		$record->meta_data = [
			'actor'  => "<script>alert(1)</script>\nadmin",
			'target' => 'settings',
		];

		$expected = ActivityLogMessageBuilder::BuildPlain(
			$record->event_slug,
			$record->meta_data
		);

		$this->assertSame(
			$expected,
			ActivityLogMessageBuilder::Build( $record->event_slug, $record->meta_data )
		);
		$this->assertSame(
			\explode( "\n", $expected ),
			ActivityLogMessageBuilder::BuildFromLogRecord( $record )
		);
		$this->assertStringContainsString( '<script>alert(1)</script> admin', $expected );
	}

	private function installControllerStub() :void {
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = new class {
			public object $events;

			public function __construct() {
				$this->events = new class {
					public function getEventAuditStrings( string $event ) :array {
						return [
							'Actor {{actor}} touched target {{target}}',
						];
					}

					public function getEventDef( string $event ) :array {
						return [
							'audit_countable' => true,
							'level'           => 'info',
						];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
