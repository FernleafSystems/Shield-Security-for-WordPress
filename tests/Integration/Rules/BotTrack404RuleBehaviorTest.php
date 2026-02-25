<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\BotTrack404,
	Conditions,
	ConditionsVO,
	Enum\EnumLogic,
	Processors\ProcessConditions,
	Processors\ResponseProcessor
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class BotTrack404RuleBehaviorTest extends ShieldIntegrationTestCase {

	private function getTrackingGateCondition() :ConditionsVO {
		$conditions = ( new BotTrack404() )->build()->conditions->conditions;
		foreach ( $conditions as $condition ) {
			if ( !$condition->is_group || $condition->logic !== EnumLogic::LOGIC_OR ) {
				continue;
			}

			$singleConditionClasses = [];
			foreach ( $condition->conditions as $subCondition ) {
				if ( $subCondition->is_single ) {
					$singleConditionClasses[] = $subCondition->conditions;
				}
			}

			if ( \in_array( Conditions\IsRequestToInvalidPlugin::class, $singleConditionClasses, true )
				 && \in_array( Conditions\IsRequestToInvalidTheme::class, $singleConditionClasses, true ) ) {
				return $condition;
			}
		}

		throw new \RuntimeException( 'Unable to locate track_404 decision gate condition.' );
	}

	private function evaluateTrackingGateForPath( string $path ) :bool {
		$con = $this->requireController();
		$con->this_req->path = $path;

		return ( new ProcessConditions( $this->getTrackingGateCondition() ) )
			->setThisRequest( $con->this_req )
			->process();
	}

	private function getRule() :\FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO {
		return ( new BotTrack404() )->build();
	}

	private function prepareAnonymous404Request( string $path, string $track404Option = 'log' ) :void {
		$con = $this->requireController();
		\wp_set_current_user( 0 );
		$con->opts->optSet( 'track_404', $track404Option );
		$con->this_req->request_bypasses_all_restrictions = false;
		$this->go_to( $path );
		$con->this_req->path = $path;
	}

	private function evaluateFullRule() :bool {
		$con = $this->requireController();
		return ( new ProcessConditions( $this->getRule()->conditions ) )
			->setThisRequest( $con->this_req )
			->process();
	}

	public function test_allowlisted_extension_png_does_not_match_tracking_gate() {
		$this->assertFalse( $this->evaluateTrackingGateForPath( '/apple-touch-icon.png' ) );
	}

	public function test_allowlisted_path_does_not_match_tracking_gate() {
		$this->assertFalse( $this->evaluateTrackingGateForPath( '/autodiscover/autodiscover.xml' ) );
	}

	public function test_non_allowlisted_path_matches_tracking_gate() {
		$this->assertTrue( $this->evaluateTrackingGateForPath( '/definitely/missing/resource.php' ) );
	}

	public function test_invalid_plugin_path_matches_tracking_gate_even_for_allowlisted_extension() {
		$pluginsPath = \rtrim( (string)\wp_parse_url( plugins_url(), \PHP_URL_PATH ), '/' );
		$this->assertNotSame( '', $pluginsPath, 'Plugins URL path must be available for this test.' );

		$this->assertTrue(
			$this->evaluateTrackingGateForPath( \sprintf(
				'%s/%s/%s',
				$pluginsPath,
				'definitely-not-a-plugin',
				'apple-touch-icon.png'
			) )
		);
	}

	public function test_full_rule_does_not_match_allowlisted_extension_404() {
		$this->prepareAnonymous404Request( '/apple-touch-icon.png', 'log' );
		$this->assertTrue( \is_404(), 'Request should be a 404 for this scenario.' );
		$this->assertFalse( $this->evaluateFullRule() );
	}

	public function test_full_rule_matches_normal_404_path() {
		$this->prepareAnonymous404Request( '/definitely/missing/resource.php', 'log' );
		$this->assertTrue( \is_404(), 'Request should be a 404 for this scenario.' );
		$this->assertTrue( $this->evaluateFullRule() );
	}

	public function test_full_rule_does_not_match_non_404_request() {
		$postId = self::factory()->post->create();
		$path = (string)\wp_parse_url( \get_permalink( $postId ), \PHP_URL_PATH );
		$path = empty( $path ) ? '/' : $path;

		$this->prepareAnonymous404Request( $path, 'log' );
		$this->assertFalse( \is_404(), 'Test fixture should resolve as a non-404 request.' );
		$this->assertFalse( $this->evaluateFullRule() );
	}

	public function test_full_rule_does_not_match_logged_in_user_on_404() {
		$userId = self::factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $userId );

		$con = $this->requireController();
		$con->opts->optSet( 'track_404', 'log' );
		$con->this_req->request_bypasses_all_restrictions = false;
		$this->go_to( '/definitely/missing/for-logged-in-user.php' );
		$con->this_req->path = '/definitely/missing/for-logged-in-user.php';

		$this->assertTrue( \is_404(), 'Request should be a 404 for this scenario.' );
		$this->assertFalse( $this->evaluateFullRule() );
	}

	public function test_normal_404_response_fires_bottrack_event_with_offense_count() {
		$this->prepareAnonymous404Request( '/definitely/missing/for-offense.php', 'transgression-single' );
		$this->assertTrue( \is_404(), 'Request should be a 404 for this scenario.' );
		$this->assertTrue( $this->evaluateFullRule(), 'Full BotTrack404 rule should match this request.' );

		$tracker = new OffenseTracker();
		$initialOffenseCount = $tracker->getOffenseCount();
		$this->captureShieldEvents();

		( new ResponseProcessor( $this->getRule() ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$events = $this->getCapturedEventsByKey( 'bottrack_404' );
		$this->assertNotEmpty( $events, 'BotTrack404 response should fire bottrack_404 event.' );
		$this->assertSame( 1, (int)( $events[ 0 ][ 'meta' ][ 'offense_count' ] ?? 0 ) );
		$this->assertSame( $initialOffenseCount + 1, $tracker->getOffenseCount() );
	}
}
