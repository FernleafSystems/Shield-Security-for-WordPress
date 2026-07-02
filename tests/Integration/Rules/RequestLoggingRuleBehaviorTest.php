<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\ShieldLogRequest as RequestLogRuleBuilder,
	Processors\ProcessConditions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class RequestLoggingRuleBehaviorTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_logger',
			'enable_limiter',
			'enable_live_log',
			'limit_requests',
			'limit_time_span',
			'live_log_started_at',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_parameterized_request_matches_when_legacy_logger_option_is_disabled() :void {
		$this->setLoggingOptions( 'N' );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-admin/admin.php?page=shield',
			],
			[
				'page' => 'shield',
			]
		);

		$this->assertTrue( $this->processRequestLogRuleConditions() );
	}

	public function test_unparameterized_request_does_not_match_without_limiter_or_live_logging() :void {
		$this->setLoggingOptions( 'N' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/wp-admin/admin.php',
		] );

		$this->assertFalse( $this->processRequestLogRuleConditions() );
	}

	private function setLoggingOptions( string $logger ) :void {
		$this->requireController()->opts
			 ->optSet( 'enable_logger', $logger )
			 ->optSet( 'enable_limiter', 'N' )
			 ->optSet( 'enable_live_log', 'N' )
			 ->optSet( 'limit_requests', 0 )
			 ->optSet( 'limit_time_span', 0 )
			 ->optSet( 'live_log_started_at', 0 );
	}

	private function processRequestLogRuleConditions() :bool {
		return ( new ProcessConditions( ( new RequestLogRuleBuilder() )->build()->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}
}
