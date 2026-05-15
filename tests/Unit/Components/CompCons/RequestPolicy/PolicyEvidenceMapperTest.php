<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	PolicyEvidence,
	PolicyEvidenceMapper
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PolicyEvidenceMapperTest extends BaseUnitTest {

	/**
	 * @dataProvider provideMappedEvents
	 */
	public function testMapsShieldEventsToPolicyEvidence( string $event, string $expectedType, string $expectedSeverity ) :void {
		$evidence = ( new PolicyEvidenceMapper() )->fromEvent( $event );

		$this->assertCount( 1, $evidence );
		$this->assertSame( $expectedType, $evidence[ 0 ]->type );
		$this->assertSame( $expectedSeverity, $evidence[ 0 ]->severity );
		$this->assertSame( $event, $evidence[ 0 ]->source_event );
	}

	public function provideMappedEvents() :array {
		return [
			'failed login'          => [ 'bottrack_loginfailed', PolicyEvidence::TYPE_AUTH_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'invalid login'         => [ 'bottrack_logininvalid', PolicyEvidence::TYPE_AUTH_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'2fa failure'           => [ '2fa_verify_fail', PolicyEvidence::TYPE_AUTH_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'2fa nonce failure'     => [ '2fa_nonce_verify_fail', PolicyEvidence::TYPE_AUTH_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'404 probe'             => [ 'bottrack_404', PolicyEvidence::TYPE_PROBE_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'xmlrpc probe'          => [ 'bottrack_xmlrpc', PolicyEvidence::TYPE_PROBE_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'author fishing'        => [ 'block_author_fishing', PolicyEvidence::TYPE_PROBE_ABUSE, PolicyEvidence::SEVERITY_CRITICAL ],
			'cooldown'              => [ 'cooldown_fail', PolicyEvidence::TYPE_RATE_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'rate limit'            => [ 'request_limit_exceeded', PolicyEvidence::TYPE_RATE_ABUSE, PolicyEvidence::SEVERITY_CRITICAL ],
			'comment spam'          => [ 'comment_spam_block', PolicyEvidence::TYPE_CONTENT_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'contact form spam'     => [ 'spam_form_fail', PolicyEvidence::TYPE_CONTENT_ABUSE, PolicyEvidence::SEVERITY_SIGNAL ],
			'offense'               => [ 'ip_offense', PolicyEvidence::TYPE_IP_ENFORCEMENT, PolicyEvidence::SEVERITY_SIGNAL ],
			'auto block event'      => [ 'ip_block_auto', PolicyEvidence::TYPE_IP_ENFORCEMENT, PolicyEvidence::SEVERITY_SIGNAL ],
			'blocked event'         => [ 'ip_blocked', PolicyEvidence::TYPE_IP_ENFORCEMENT, PolicyEvidence::SEVERITY_SIGNAL ],
		];
	}

	public function testFirewallCategoryControlsSeverity() :void {
		$mapper = new PolicyEvidenceMapper();

		$noisy = $mapper->fromEvent( 'firewall_block', [
			'audit_params' => [
				'scan' => 'sql_queries',
			],
		] );
		$critical = $mapper->fromEvent( 'firewall_block', [
			'audit_params' => [
				'scan' => 'php_code',
			],
		] );

		$this->assertSame( PolicyEvidence::TYPE_FIREWALL_ABUSE, $noisy[ 0 ]->type );
		$this->assertSame( PolicyEvidence::SEVERITY_NOISY, $noisy[ 0 ]->severity );
		$this->assertSame( PolicyEvidence::SEVERITY_CRITICAL, $critical[ 0 ]->severity );
	}

	public function testBotTrackMultipleExpandsMappedChildEvents() :void {
		$evidence = ( new PolicyEvidenceMapper() )->fromEvent( 'bottrack_multiple', [
			'data' => [
				'events' => [
					'bottrack_404',
					'bottrack_loginfailed',
					'login_success',
				],
			],
		] );

		$this->assertSame(
			[ PolicyEvidence::TYPE_PROBE_ABUSE, PolicyEvidence::TYPE_AUTH_ABUSE ],
			\array_map( static fn( PolicyEvidence $item ) :string => $item->type, $evidence )
		);
	}

	/**
	 * @dataProvider provideIgnoredEvents
	 */
	public function testIgnoredEventsDoNotProducePolicyEvidence( string $event ) :void {
		$this->assertSame( [], ( new PolicyEvidenceMapper() )->fromEvent( $event ) );
	}

	public function provideIgnoredEvents() :array {
		return [
			'decision'      => [ 'request_policy_decision' ],
			'policy block'  => [ 'request_policy_block' ],
			'notbot'        => [ 'bottrack_notbot' ],
			'login success' => [ 'login_success' ],
			'manual block'  => [ 'ip_block_manual' ],
			'mark spam'     => [ 'comment_markspam' ],
			'unmark spam'   => [ 'comment_unmarkspam' ],
		];
	}
}
