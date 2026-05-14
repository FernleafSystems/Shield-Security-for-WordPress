<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\{
	BlockIpAddressCrowdsec,
	BlockIpAddressShield
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\HighReputationIp,
	Build\Core\IpBlockedCrowdsec,
	Build\Core\IpBlockedShield,
	Build\Core\IpWhitelisted,
	Conditions,
	ConditionsVO,
	Processors\ProcessConditions,
	Responses,
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class IpDecisionRulesBehaviorTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'bot_signals' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'antibot_high_reputation_minimum',
			'cs_block',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		Transient::Delete( 'shield-bot-scoring-logic' );
	}

	public function tear_down() {
		\remove_filter( 'shield/is_ip_blocked_auto', '__return_false' );
		Transient::Delete( 'shield-bot-scoring-logic' );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	/**
	 * @dataProvider shieldBlockProvider
	 */
	public function test_shield_block_rule_contract_for_manual_and_auto_blocks(
		string $ip,
		callable $seedBlock
	) :void {
		$seedBlock( $ip );
		$this->applyRequestForIp( $ip, [
			'request_bypasses_all_restrictions' => false,
		] );

		$rule = ( new IpBlockedShield() )->build();

		$this->assertSame( IpBlockedShield::SLUG, $rule->slug );
		$this->assertTrue( $this->evaluateRule( $rule ) );
		$this->assertSame( 'conn_kill', $this->responseParam( $rule, Responses\EventFire::class, 'event' ) );
		$this->assertSame(
			BlockIpAddressShield::SLUG,
			$this->responseParam( $rule, Responses\DisplayBlockPage::class, 'block_page_slug' )
		);
	}

	public function test_shield_block_rule_does_not_match_bypassed_blocked_ip() :void {
		$ip = '10.0.20.12';
		TestDataFactory::insertManualBlock( $ip );
		TestDataFactory::insertBypass( $ip );
		$this->applyRequestForIp( $ip );

		$this->assertFalse( $this->evaluateRule( ( new IpBlockedShield() )->build() ) );
	}

	public function test_crowdsec_block_rule_contract_and_disabled_negative() :void {
		$ip = '10.0.20.13';
		TestDataFactory::insertCrowdsecBlock( $ip );
		$this->requireController()->opts->optSet( 'cs_block', 'block' );
		$this->applyRequestForIp( $ip, [
			'request_bypasses_all_restrictions' => false,
		] );

		$rule = ( new IpBlockedCrowdsec() )->build();

		$this->assertSame( IpBlockedCrowdsec::SLUG, $rule->slug );
		$this->assertTrue( $this->evaluateRule( $rule ) );
		$this->assertSame( 'conn_kill_crowdsec', $this->responseParam( $rule, Responses\EventFire::class, 'event' ) );
		$this->assertSame(
			BlockIpAddressCrowdsec::SLUG,
			$this->responseParam( $rule, Responses\DisplayBlockPage::class, 'block_page_slug' )
		);

		$this->requireController()->opts->optSet( 'cs_block', 'disabled' );
		$this->applyRequestForIp( $ip, [
			'request_bypasses_all_restrictions' => false,
		] );
		$this->assertFalse( $this->evaluateRule( ( new IpBlockedCrowdsec() )->build() ) );
	}

	public function test_ip_whitelisted_rule_and_request_bypass_condition_are_direct_contracts() :void {
		$bypassedIp = '10.0.20.14';
		TestDataFactory::insertBypass( $bypassedIp );
		$this->applyRequestForIp( $bypassedIp );

		$rule = ( new IpWhitelisted() )->build();
		$this->assertSame( IpWhitelisted::SLUG, $rule->slug );
		$this->assertTrue( $this->evaluateRule( $rule ) );
		$this->responseDefinition( $rule, Responses\UpdateIpRuleLastAccessAt::class );
		$this->assertTrue( $this->evaluateCondition( Conditions\IsIpWhitelisted::class ) );
		$this->assertTrue( $this->evaluateCondition( Conditions\RequestBypassesAllRestrictions::class ) );

		$blockedIp = '10.0.20.15';
		TestDataFactory::insertManualBlock( $blockedIp );
		$this->applyRequestForIp( $blockedIp );
		$this->assertFalse( $this->evaluateCondition( Conditions\IsIpWhitelisted::class ) );
		$this->assertFalse( $this->evaluateCondition( Conditions\RequestBypassesAllRestrictions::class ) );
	}

	public function test_high_reputation_rule_prevents_auto_block_contract() :void {
		$ip = '10.0.20.16';
		$now = Services::Request()->ts();
		$this->requireController()->opts->optSet( 'antibot_high_reputation_minimum', 200 );
		TestDataFactory::insertAutoBlock( $ip );
		TestDataFactory::insertBotSignal( $ip, [
			'auth_at'   => $now - 60,
			'altcha_at' => $now - 45,
			'notbot_at' => $now - 30,
		] );
		$this->applyRequestForIp( $ip );

		$rule = ( new HighReputationIp() )->build();

		$this->assertSame( HighReputationIp::SLUG, $rule->slug );
		$this->assertTrue( $this->evaluateRule( $rule ) );
		$this->responseDefinition( $rule, Responses\PreventShieldIpAutoBlock::class );
		$this->assertTrue( $this->evaluateCondition( Conditions\IsIpHighReputation::class ) );
		$this->assertFalse( $this->requireController()->this_req->is_ip_blocked_shield_auto );

		( new Responses\PreventShieldIpAutoBlock() )
			->setThisRequest( $this->requireController()->this_req )
			->execResponse();
		$this->assertFalse( \apply_filters( 'shield/is_ip_blocked_auto', true ) );
	}

	public function test_high_reputation_does_not_mask_manual_or_crowdsec_blocks() :void {
		$manualIp = '10.0.20.17';
		$crowdsecIp = '10.0.20.18';
		$now = Services::Request()->ts();
		$this->requireController()->opts->optSet( 'antibot_high_reputation_minimum', 200 );

		TestDataFactory::insertManualBlock( $manualIp );
		TestDataFactory::insertCrowdsecBlock( $crowdsecIp );
		foreach ( [ $manualIp, $crowdsecIp ] as $ip ) {
			TestDataFactory::insertBotSignal( $ip, [
				'auth_at'   => $now - 60,
				'altcha_at' => $now - 45,
				'notbot_at' => $now - 30,
			] );
		}

		$this->applyRequestForIp( $manualIp, [
			'request_bypasses_all_restrictions' => false,
		] );
		$this->assertTrue( $this->requireController()->this_req->is_ip_high_reputation );
		$this->assertTrue( $this->requireController()->this_req->is_ip_blocked_shield_manual );
		$this->assertTrue( $this->evaluateRule( ( new IpBlockedShield() )->build() ) );

		$this->requireController()->opts->optSet( 'cs_block', 'block' );
		$this->applyRequestForIp( $crowdsecIp, [
			'request_bypasses_all_restrictions' => false,
		] );
		$this->assertTrue( $this->requireController()->this_req->is_ip_high_reputation );
		$this->assertTrue( $this->requireController()->this_req->is_ip_blocked_crowdsec );
		$this->assertTrue( $this->evaluateRule( ( new IpBlockedCrowdsec() )->build() ) );
	}

	public function shieldBlockProvider() :array {
		return [
			'manual block' => [
				'10.0.20.10',
				static fn( string $ip ) => TestDataFactory::insertManualBlock( $ip ),
			],
			'auto block'   => [
				'10.0.20.11',
				static fn( string $ip ) => TestDataFactory::insertAutoBlock( $ip ),
			],
		];
	}

	private function applyRequestForIp( string $ip, array $overrides = [] ) :void {
		$this->resetIpCaches();
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/',
				'REMOTE_ADDR'    => $ip,
			],
			[],
			[],
			\array_merge( [
				'request_bypasses_all_restrictions'  => null,
				'request_subject_to_shield_restrictions' => true,
				'is_site_lockdown_active'            => false,
				'is_site_lockdown_blocked'           => false,
			], $overrides )
		);
	}

	private function evaluateRule( RuleVO $rule ) :bool {
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function evaluateCondition( string $conditionClass ) :bool {
		return ( new ProcessConditions( ( new ConditionsVO() )->applyFromArray( [
			'conditions' => $conditionClass,
		] ) ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function responseDefinition( RuleVO $rule, string $responseClass ) :array {
		foreach ( $rule->responses as $response ) {
			$this->assertArrayHasKey( 'response', $response );
			if ( $response[ 'response' ] === $responseClass ) {
				return $response;
			}
		}

		$this->fail( 'Rule must define response: '.$responseClass );
	}

	private function responseParam( RuleVO $rule, string $responseClass, string $paramKey ) :mixed {
		$response = $this->responseDefinition( $rule, $responseClass );
		$this->assertArrayHasKey( 'params', $response );
		$this->assertArrayHasKey( $paramKey, $response[ 'params' ] );
		return $response[ 'params' ][ $paramKey ];
	}
}
