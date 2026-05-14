<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack\TrackLinkCheese;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\BotTrackInvalidScript,
	Build\Core\BotTrackXmlrpc,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class BotTrackRemainingActionsTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	private $permalinkSnapshot = null;

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'track_xmlrpc',
			'track_invalidscript',
			'track_linkcheese',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->permalinkSnapshot = \get_option( 'permalink_structure' );
		\update_option( 'permalink_structure', '' );
		$this->enablePremiumCapabilities();
	}

	public function tear_down() {
		\update_option( 'permalink_structure', $this->permalinkSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		\wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * @dataProvider botTrackModeProvider
	 */
	public function test_xmlrpc_bot_tracking_uses_mode_contract(
		string $mode,
		int $expectedOffenseCount,
		bool $expectedBlock
	) :void {
		$this->prepareXmlrpcRequest( $mode );
		$this->assertTrue( $this->evaluateRule( $this->xmlrpcRule() ) );
		$this->assertRuleResponseEvent( $this->xmlrpcRule(), 'bottrack_xmlrpc', $expectedOffenseCount, $expectedBlock );
	}

	public function test_xmlrpc_bot_tracking_does_not_match_disabled_bypassed_or_logged_in_requests() :void {
		$this->prepareXmlrpcRequest( 'disabled' );
		$this->assertFalse( $this->evaluateRule( $this->xmlrpcRule() ) );

		$this->prepareXmlrpcRequest( 'log' );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;
		$this->assertFalse( $this->evaluateRule( $this->xmlrpcRule() ) );

		$this->prepareXmlrpcRequest( 'log' );
		\wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( $this->evaluateRule( $this->xmlrpcRule() ) );
	}

	/**
	 * @dataProvider botTrackModeProvider
	 */
	public function test_invalid_script_bot_tracking_uses_mode_contract(
		string $mode,
		int $expectedOffenseCount,
		bool $expectedBlock
	) :void {
		$this->prepareInvalidScriptRequest( $mode );
		$this->assertTrue( $this->evaluateRule( $this->invalidScriptRule() ) );
		$this->assertRuleResponseEvent( $this->invalidScriptRule(), 'bottrack_invalidscript', $expectedOffenseCount, $expectedBlock );
	}

	public function test_invalid_script_bot_tracking_does_not_match_disabled_bypassed_logged_in_or_allowed_scripts() :void {
		$this->prepareInvalidScriptRequest( 'disabled' );
		$this->assertFalse( $this->evaluateRule( $this->invalidScriptRule() ) );

		$this->prepareInvalidScriptRequest( 'log' );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;
		$this->assertFalse( $this->evaluateRule( $this->invalidScriptRule() ) );

		$this->prepareInvalidScriptRequest( 'log' );
		\wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( $this->evaluateRule( $this->invalidScriptRule() ) );

		$this->prepareInvalidScriptRequest( 'log', 'index.php' );
		$this->assertFalse( $this->evaluateRule( $this->invalidScriptRule() ) );
	}

	/**
	 * @dataProvider botTrackModeProvider
	 */
	public function test_link_cheese_bot_tracking_uses_mode_contract(
		string $mode,
		int $expectedOffenseCount,
		bool $expectedBlock
	) :void {
		$this->prepareLinkCheeseRequest( $mode );

		$tracker = new OffenseTracker();
		$initialOffenseCount = $tracker->getOffenseCount();
		$this->captureShieldEvents();

		( new TrackLinkCheese() )->testCheese();

		$events = $this->getCapturedEventsByKey( 'bottrack_linkcheese' );
		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'offense_count', $events[ 0 ][ 'meta' ] );
		$this->assertArrayHasKey( 'block', $events[ 0 ][ 'meta' ] );
		$this->assertSame( $expectedOffenseCount, (int)$events[ 0 ][ 'meta' ][ 'offense_count' ] );
		$this->assertSame( $expectedBlock, (bool)$events[ 0 ][ 'meta' ][ 'block' ] );
		$this->assertSame( $initialOffenseCount + $expectedOffenseCount, $tracker->getOffenseCount() );
	}

	public function test_link_cheese_disabled_option_does_not_register_tracker() :void {
		$this->requireController()->opts->optSet( 'track_linkcheese', 'disabled' );
		$this->assertNotContains( TrackLinkCheese::class, $this->enumerateBotTrackers() );

		$this->requireController()->opts->optSet( 'track_linkcheese', 'log' );
		$this->assertContains( TrackLinkCheese::class, $this->enumerateBotTrackers() );
	}

	public function test_link_cheese_does_not_track_logged_in_request() :void {
		$this->prepareLinkCheeseRequest( 'log' );
		\wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->captureShieldEvents();

		( new TrackLinkCheese() )->testCheese();

		$this->assertSame( [], $this->getCapturedEventsByKey( 'bottrack_linkcheese' ) );
	}

	public function test_link_cheese_tracks_bypassed_anonymous_request() :void {
		$this->prepareLinkCheeseRequest( 'log' );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;
		$this->captureShieldEvents();

		( new TrackLinkCheese() )->testCheese();

		$events = $this->getCapturedEventsByKey( 'bottrack_linkcheese' );
		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'offense_count', $events[ 0 ][ 'meta' ] );
		$this->assertArrayHasKey( 'block', $events[ 0 ][ 'meta' ] );
		$this->assertSame( 0, (int)$events[ 0 ][ 'meta' ][ 'offense_count' ] );
		$this->assertFalse( (bool)$events[ 0 ][ 'meta' ][ 'block' ] );
	}

	public function botTrackModeProvider() :array {
		return [
			'log'                  => [ 'log', 0, false ],
			'transgression-single' => [ 'transgression-single', 1, false ],
			'transgression-double' => [ 'transgression-double', 2, false ],
			'block'                => [ 'block', 1, true ],
		];
	}

	private function prepareXmlrpcRequest( string $trackOption ) :void {
		\wp_set_current_user( 0 );
		$con = $this->requireController();
		$con->opts->optSet( 'track_xmlrpc', $trackOption );
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->wp_is_xmlrpc = true;
	}

	private function prepareInvalidScriptRequest( string $trackOption, string $scriptName = 'definitely-not-allowed.php' ) :void {
		\wp_set_current_user( 0 );
		$con = $this->requireController();
		$con->opts->optSet( 'track_invalidscript', $trackOption );
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->script_name = $scriptName;
	}

	private function prepareLinkCheeseRequest( string $trackOption ) :void {
		\wp_set_current_user( 0 );
		$con = $this->requireController();
		$con->opts->optSet( 'track_linkcheese', $trackOption );

		$cheeseWord = $con->prefix( 'link-cheese' );
		$this->go_to( '/?'.$cheeseWord.'=1' );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?'.$cheeseWord.'=1',
			],
			[
				$cheeseWord => '1',
			],
			[],
			[
				'path' => '/',
			]
		);
	}

	private function evaluateRule( RuleVO $rule ) :bool {
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function assertRuleResponseEvent(
		RuleVO $rule,
		string $eventKey,
		int $expectedOffenseCount,
		bool $expectedBlock
	) :void {
		$tracker = new OffenseTracker();
		$initialOffenseCount = $tracker->getOffenseCount();
		$this->captureShieldEvents();

		( new ResponseProcessor( $rule ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$events = $this->getCapturedEventsByKey( $eventKey );
		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'offense_count', $events[ 0 ][ 'meta' ] );
		$this->assertArrayHasKey( 'block', $events[ 0 ][ 'meta' ] );
		$this->assertSame( $expectedOffenseCount, (int)$events[ 0 ][ 'meta' ][ 'offense_count' ] );
		$this->assertSame( $expectedBlock, (bool)$events[ 0 ][ 'meta' ][ 'block' ] );
		$this->assertSame( $initialOffenseCount + $expectedOffenseCount, $tracker->getOffenseCount() );
	}

	private function xmlrpcRule() :RuleVO {
		return ( new BotTrackXmlrpc() )->build();
	}

	private function invalidScriptRule() :RuleVO {
		return ( new BotTrackInvalidScript() )->build();
	}

	private function enumerateBotTrackers() :array {
		$controller = new BotSignalsController();
		$method = new \ReflectionMethod( $controller, 'enumerateBotTrackers' );
		$method->setAccessible( true );

		return $method->invoke( $controller );
	}
}
