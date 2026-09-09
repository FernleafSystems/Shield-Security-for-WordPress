<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use DOMDocument;
use DOMXPath;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestGeneral,
	UnitTestIpUtils,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestSvgs
};
use FernleafSystems\Wordpress\Services\Core\Users;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class ActivityLogTableDisplayTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );

		$this->installControllerStub();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_ip'        => new UnitTestIpUtils(),
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpgeneral' => new UnitTestGeneral( '/admin-ajax.php', 'display:' ),
			'service_wpusers'   => new class extends Users {
				public function getAdminUrl_ProfileEdit( $user = null ) :string {
					if ( \is_object( $user ) && isset( $user->profile_url ) ) {
						return (string)$user->profile_url;
					}
					$uid = \is_object( $user ) && isset( $user->ID ) ? (int)$user->ID : 0;
					return '/wp-admin/user-edit.php?user_id='.$uid;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_activity_identity_uses_badges_for_visitor_ip_raw_ip_and_user() :void {
		$row = $this->buildRow(
			$this->createBuilder(
				[ '2001:db8::1|' => [ IpID::VISITOR, 'Visitor' ] ],
				[ 7 => $this->makeUser( 7, 'admin-user' ) ]
			),
			$this->makeLogRecord( '2001:db8::1', [ 'uid' => '7' ] )
		);

		$identity = (string)$row[ 'identity' ];
		$xpath = $this->identityXPath( $identity );
		$this->assertIdentityBadgesDoNotUseBootstrapBadge( $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__ip', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__primary', 'activity-log-identity__badge--source', 'div', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__primary', 'activity-log-identity__badge--user', 'div', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__ip', 'activity-log-identity__badge--ip', 'div', 'span' ), $xpath );
		$this->assertXPathCount( 3, $this->classQuery( 'activity-log-identity__badge-label', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__badge--source', 'span' ).'//i[contains(@class, "bi-cloud-check")]', $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__badge--user', 'span' ).'//i[contains(@class, "bi-person")]', $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__badge--ip', 'span' ).'//i[contains(@class, "bi-globe2")]', $xpath );
		$this->assertXPathContainsText( 'Your IP', $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathContainsText( 'admin-user', $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__ip', 'div' ).'//a[@data-ip="2001:db8::1"]', $xpath );
		$this->assertXPathContainsText( '2001:db8::1', $this->classQuery( 'activity-log-identity__ip', 'div' ), $xpath );
		$this->assertStringNotContainsString(
			'2001:db8::1',
			$this->xpathText( $xpath, $this->classQuery( 'activity-log-identity__primary', 'div' ) )
		);
		$this->assertStringNotContainsString( 'Your Current IP', $identity );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_identity_uses_service_identity_badge_without_unauthenticated_filler() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'203.0.113.44|' => [ 'google', 'Googlebot' ],
			] ),
			$this->makeLogRecord( '203.0.113.44' )
		);

		$identity = (string)$row[ 'identity' ];
		$xpath = $this->identityXPath( $identity );
		$this->assertIdentityBadgesDoNotUseBootstrapBadge( $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__primary', 'activity-log-identity__badge--source', 'div', 'span' ), $xpath );
		$this->assertXPathCount( 0, $this->classQuery( 'activity-log-identity__badge--user', 'span' ), $xpath );
		$this->assertXPathContainsText( 'Googlebot', $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathContainsText( '203.0.113.44', $this->classQuery( 'activity-log-identity__ip', 'div' ), $xpath );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_identity_renders_known_actor_badge() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'203.0.113.60|' => [ IpID::UNKNOWN, 'Unknown' ],
			] ),
			$this->makeLogRecord( '203.0.113.60', [ 'uid' => 'cron' ] )
		);

		$identity = (string)$row[ 'identity' ];
		$xpath = $this->identityXPath( $identity );
		$this->assertIdentityBadgesDoNotUseBootstrapBadge( $xpath );
		$this->assertXPathCount( 0, $this->classQuery( 'activity-log-identity__badge--source', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__primary', 'activity-log-identity__badge--user', 'div', 'span' ), $xpath );
		$this->assertXPathContainsText( 'WP Cron', $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_identity_suppresses_unknown_identity_badge_but_keeps_raw_ip() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'198.51.100.20|' => [ IpID::UNKNOWN, 'Unknown' ],
			] ),
			$this->makeLogRecord( '198.51.100.20' )
		);

		$identity = (string)$row[ 'identity' ];
		$xpath = $this->identityXPath( $identity );
		$this->assertIdentityBadgesDoNotUseBootstrapBadge( $xpath );
		$this->assertXPathCount( 0, $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathCount( 0, $this->classQuery( 'activity-log-identity__badge--source', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__ip', 'activity-log-identity__badge--ip', 'div', 'span' ), $xpath );
		$this->assertXPathContainsText( '198.51.100.20', $this->classQuery( 'activity-log-identity__ip', 'div' ), $xpath );
		$this->assertXPathTextNotContains( 'Unknown', $this->classQuery( 'activity-log-identity', 'div' ), $xpath );
		$this->assertXPathTextNotContains( 'Unidentified', $this->classQuery( 'activity-log-identity', 'div' ), $xpath );
		$this->assertStringNotContainsString( 'shield-ipanalyse-section-fallback', $identity );
	}

	public function test_activity_identity_keeps_actor_badge_when_ip_is_missing() :void {
		$row = $this->buildRow(
			$this->createBuilder( [] ),
			$this->makeLogRecord( '', [ 'uid' => 'cron' ] )
		);

		$identity = (string)$row[ 'identity' ];
		$xpath = $this->identityXPath( $identity );
		$this->assertIdentityBadgesDoNotUseBootstrapBadge( $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__primary', 'activity-log-identity__badge--user', 'div', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__ip', 'activity-log-identity__badge--no-ip', 'div', 'span' ), $xpath );
		$this->assertXPathContainsText( 'WP Cron', $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathContainsText( 'No IP', $this->classQuery( 'activity-log-identity__ip', 'div' ), $xpath );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_date_displays_relative_time_with_full_timestamp_tooltip() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'198.51.100.20|' => [ IpID::UNKNOWN, 'Unknown' ],
			] ),
			$this->makeLogRecord( '198.51.100.20', [], 1713278100 )
		);

		$date = $row[ 'created_since' ];
		$this->assertStringContainsString( 'class="activity-log-date"', $date );
		$this->assertStringContainsString( 'data-bs-toggle="tooltip"', $date );
		$this->assertStringContainsString( 'data-bs-title="display:1713278100"', $date );
		$this->assertStringNotContainsString( '<br', $date );
		$this->assertStringNotContainsString( '<small', $date );
	}

	public function test_investigation_activity_table_inherits_shared_identity_and_date_display() :void {
		$row = $this->buildRow(
			$this->createInvestigationBuilder( [
				'203.0.113.7|' => [ IpID::THIS_SERVER, 'Server' ],
			] ),
			$this->makeLogRecord( '203.0.113.7', [], 1713278100 )
		);

		$xpath = $this->identityXPath( (string)$row[ 'identity' ] );
		$this->assertIdentityBadgesDoNotUseBootstrapBadge( $xpath );
		$this->assertXPathContainsText( 'This Server', $this->classQuery( 'activity-log-identity__primary', 'div' ), $xpath );
		$this->assertXPathCount( 1, $this->descendantClassQuery( 'activity-log-identity__primary', 'activity-log-identity__badge--source', 'div', 'span' ), $xpath );
		$this->assertXPathCount( 1, $this->classQuery( 'activity-log-identity__ip', 'div' ), $xpath );
		$this->assertXPathCount( 0, $this->classQuery( 'investigate-ip-deeplink', 'a' ), $xpath );
		$this->assertStringContainsString( 'data-bs-title="display:1713278100"', $row[ 'created_since' ] );
	}

	public function test_activity_row_escapes_event_message_request_id_and_user_link_output() :void {
		$row = $this->buildRow(
			$this->createBuilder( [], [
				9 => $this->makeUser(
					9,
					'user"><img src=x onerror=alert(1)>',
					'/wp-admin/user-edit.php?user_id=9&next="><script>alert(1)</script>'
				),
			] ),
			$this->makeLogRecord(
				'',
				[
					'uid'    => '9',
					'detail' => "<button onclick=alert(2)>push</button>\nSecond line",
				],
				1713278000,
				'malicious_event',
				'rid" onclick="alert(3)'
			)
		);

		$this->assertSame( 'Bad &lt;script&gt;Event&lt;/script&gt;', $row[ 'event' ] );
		$this->assertStringContainsString( 'Bad &lt;script&gt;Event&lt;/script&gt;', $row[ 'message' ] );
		$this->assertStringContainsString( '&lt;button onclick=alert(2)&gt;push&lt;/button&gt; Second line', $row[ 'message' ] );
		$this->assertStringNotContainsString( '<script>Event</script>', $row[ 'message' ] );
		$this->assertStringNotContainsString( '<button onclick', $row[ 'message' ] );
		$this->assertStringContainsString( 'data-rid="rid&quot; onclick=&quot;alert(3)"', $row[ 'meta' ] );
		$this->assertStringNotContainsString( 'data-rid="rid" onclick="alert(3)"', $row[ 'meta' ] );
		$this->assertStringContainsString(
			'href="/wp-admin/user-edit.php?user_id=9&amp;next=&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"',
			$row[ 'user' ]
		);
		$this->assertStringContainsString( 'user&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $row[ 'user' ] );
		$this->assertStringNotContainsString( '<img src=x', $row[ 'user' ] );
	}

	private function identityXPath( string $html ) :DOMXPath {
		$doc = new DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		$doc->loadHTML( '<!doctype html><html><body>'.$html.'</body></html>' );
		\libxml_clear_errors();
		\libxml_use_internal_errors( $previous );
		return new DOMXPath( $doc );
	}

	private function classQuery( string $class, string $node = '*' ) :string {
		return \sprintf(
			'//%s[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
			$node,
			$class
		);
	}

	private function descendantClassQuery(
		string $ancestorClass,
		string $descendantClass,
		string $ancestorNode = '*',
		string $descendantNode = '*'
	) :string {
		return \sprintf(
			'%s//%s[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
			$this->classQuery( $ancestorClass, $ancestorNode ),
			$descendantNode,
			$descendantClass
		);
	}

	private function assertIdentityBadgesDoNotUseBootstrapBadge( DOMXPath $xpath ) :void {
		$badges = $xpath->query( $this->classQuery( 'activity-log-identity__badge', 'span' ) );
		foreach ( $badges as $badge ) {
			$this->assertNotContains(
				'badge',
				\preg_split( '#\s+#', \trim( $badge->getAttribute( 'class' ) ) ) ?: [],
				'Activity Log identity badges must not use Bootstrap badge styling.'
			);
		}
	}

	private function assertXPathCount( int $expected, string $query, DOMXPath $xpath ) :void {
		$this->assertSame( $expected, $xpath->query( $query )->count(), 'Unexpected match count for XPath: '.$query );
	}

	private function assertXPathContainsText( string $expected, string $query, DOMXPath $xpath ) :void {
		$this->assertStringContainsString( $expected, $this->xpathText( $xpath, $query ) );
	}

	private function assertXPathTextNotContains( string $expected, string $query, DOMXPath $xpath ) :void {
		$this->assertStringNotContainsString( $expected, $this->xpathText( $xpath, $query ) );
	}

	private function xpathText( DOMXPath $xpath, string $query ) :string {
		$node = $xpath->query( $query )->item( 0 );
		return $node === null ? '' : $node->textContent;
	}

	private function buildRow( BuildActivityLogTableData $builder, LogRecord $record ) :array {
		return $builder->exportBuildTableRowsFromRawRecords( [ $record ] )[ 0 ];
	}

	private function createBuilder( array $identityResults, array $users = [] ) :BuildActivityLogTableData {
		return new class( $identityResults, $users ) extends BuildActivityLogTableData {
			use ActivityLogTableDisplayBuilderOverrides;
		};
	}

	private function createInvestigationBuilder( array $identityResults, array $users = [] ) :InvestigationActivityLogTableData {
		return new class( $identityResults, $users ) extends InvestigationActivityLogTableData {
			use ActivityLogTableDisplayBuilderOverrides;
		};
	}

	private function makeLogRecord(
		string $ip,
		array $metaData = [],
		int $timestamp = 1713278000,
		string $eventSlug = 'test_event',
		string $rid = 'req-test'
	) :LogRecord {
		$record = new LogRecord();
		$record->event_slug = $eventSlug;
		$record->ip = $ip;
		$record->rid = $rid;
		$record->created_at = $timestamp;
		$record->updated_at = $timestamp;
		$record->meta_data = $metaData;
		return $record;
	}

	private function makeUser( int $id, string $login, string $profileUrl = '' ) :object {
		$user = (object)[
			'ID'         => $id,
			'user_login' => $login,
		];
		if ( $profileUrl !== '' ) {
			$user->profile_url = $profileUrl;
		}
		return $user;
	}

	private function installControllerStub() :void {
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		$controller->svgs = new UnitTestSvgs();
		$controller->comps = new class {
			public object $events;

			public function __construct() {
				$this->events = new class {
					public function getEventName( string $event ) :string {
						return $event === 'malicious_event' ? 'Bad <script>Event</script>' : $event;
					}

					public function getEventAuditStrings( string $event ) :array {
						if ( $event === 'malicious_event' ) {
							return [ 'Changed {{detail}}' ];
						}
						return [ 'Event '.$event ];
					}

					public function getEventDef( string $event ) :array {
						return [
							'audit_countable' => false,
							'level'           => 'info',
						];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}

trait ActivityLogTableDisplayBuilderOverrides {

	private array $identityResults;

	private array $users;

	public function __construct( array $identityResults, array $users ) {
		$this->identityResults = $identityResults;
		$this->users = $users;
	}

	protected function createIpIdentifier( string $ip, ?string $userAgent = null ) :IpID {
		$key = $ip.'|'.\trim( (string)$userAgent );
		if ( !\array_key_exists( $key, $this->identityResults ) ) {
			throw new \RuntimeException( 'Unexpected identity lookup for '.$key );
		}

		return new class( $this->identityResults[ $key ] ) extends IpID {
			private array $result;

			public function __construct( array $result ) {
				parent::__construct( '127.0.0.1' );
				$this->result = $result;
			}

			public function run() :array {
				return $this->result;
			}
		};
	}

	protected function resolveUser( int $uid ) {
		return $this->users[ $uid ] ?? null;
	}
}
