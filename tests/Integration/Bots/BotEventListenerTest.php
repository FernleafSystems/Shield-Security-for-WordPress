<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotEventListener;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Tests that BotEventListener maps specific events to the correct
 * signal columns in the bot_signals table.
 */
class BotEventListenerTest extends ShieldIntegrationTestCase {

	/**
	 * Complete mapping from BotEventListener::getEventsToColumn().
	 *
	 * @return array<string, array{string, string}>
	 */
	public function provideEventToColumnMappings() :array {
		return [
			'not-bot signal'      => [ 'bottrack_notbot', 'notbot_at' ],
			'altcha'              => [ 'bottrack_altcha', 'altcha_at' ],
			'frontpage load'      => [ 'frontpage_load', 'frontpage_at' ],
			'login page load'     => [ 'loginpage_load', 'loginpage_at' ],
			'404 tracking'        => [ 'bottrack_404', 'bt404_at' ],
			'fake web crawler'    => [ 'bottrack_fakewebcrawler', 'btfake_at' ],
			'link cheese'         => [ 'bottrack_linkcheese', 'btcheese_at' ],
			'login failed'        => [ 'bottrack_loginfailed', 'btloginfail_at' ],
			'user agent'          => [ 'bottrack_useragent', 'btua_at' ],
			'xmlrpc'              => [ 'bottrack_xmlrpc', 'btxml_at' ],
			'login invalid'       => [ 'bottrack_logininvalid', 'btlogininvalid_at' ],
			'invalid script'      => [ 'bottrack_invalidscript', 'btinvalidscript_at' ],
			'author fishing'      => [ 'block_author_fishing', 'btauthorfishing_at' ],
			'cooldown fail'       => [ 'cooldown_fail', 'cooldown_at' ],
			'rate limit'          => [ 'request_limit_exceeded', 'ratelimit_at' ],
			'human spam'          => [ 'spam_block_human', 'humanspam_at' ],
			'mark spam'           => [ 'comment_markspam', 'markspam_at' ],
			'unmark spam'         => [ 'comment_unmarkspam', 'unmarkspam_at' ],
			'firewall block'      => [ 'firewall_block', 'firewall_at' ],
			'ip offense'          => [ 'ip_offense', 'offense_at' ],
			'ip blocked'          => [ 'ip_blocked', 'blocked_at' ],
			'ip unblock'          => [ 'ip_unblock', 'unblocked_at' ],
			'ip bypass add'       => [ 'ip_bypass_add', 'bypass_at' ],
			'login success'       => [ 'login_success', 'auth_at' ],
		];
	}

	/**
	 * @dataProvider provideEventToColumnMappings
	 */
	public function test_event_updates_correct_signal_column( string $event, string $expectedColumn ) {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$ip = '192.0.2.210';

		// Create a bot signal record with all zeros
		TestDataFactory::insertBotSignal( $ip );

		$listener = new BotEventListener();
		$listener->fireEventForIP( $ip, $event );

		// Verify the column was updated
		$dbh = $this->requireController()->db_con->bot_signals;
		$ipRecord = TestDataFactory::createIpRecord( $ip );

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$record = $select->filterByIP( $ipRecord->id )->first();

		$this->assertNotEmpty( $record, 'Bot signal record should exist after event' );
		$this->assertGreaterThan( 0, (int)$record->{$expectedColumn},
			"Event '{$event}' should update column '{$expectedColumn}'" );
	}

	public function test_unknown_event_does_not_update_signals() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$ip = '192.0.2.211';
		$now = Services::Request()->ts();

		$id = TestDataFactory::insertBotSignal( $ip );

		$listener = new BotEventListener();
		$listener->fireEventForIP( $ip, 'some_irrelevant_event_xyz' );

		// Record should be unchanged
		$dbh = $this->requireController()->db_con->bot_signals;
		$record = $dbh->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );

		// All signal columns should still be 0 (default)
		$signalColumns = [
			'notbot_at', 'bt404_at', 'btfake_at', 'btloginfail_at',
			'btxml_at', 'auth_at', 'offense_at', 'blocked_at',
		];
		foreach ( $signalColumns as $col ) {
			$this->assertSame( 0, (int)$record->{$col},
				"Column '{$col}' should remain 0 for unknown event" );
		}
	}
}
