<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BotEventListener {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return !self::con()->this_req->is_trusted_request && self::con()->db_con->bot_signals->isReady();
	}

	protected function run() {
		add_action( 'shield/event', function ( string $event, array $meta = [] ) {
			if ( $event === 'bottrack_multiple' && !empty( $meta[ 'data' ][ 'events' ] ) ) {
				$this->fireEventsForIP( self::con()->this_req->ip, $meta[ 'data' ][ 'events' ] );
			}
			else {
				$this->fireEventForIP( self::con()->this_req->ip, $event );
			}
		}, 10, 2 );
	}

	public function fireEventForIP( $ip, $event ) {
		$this->fireEventsForIP( $ip, [ $event ] );
	}

	public function fireEventsForIP( $ip, array $events ) {
		$signalFields = \array_values( \array_intersect_key( $this->getEventsToColumn(), \array_flip( $events ) ) );

		if ( !empty( $signalFields ) ) {
			try {
				( new BotSignalsRecord() )
					->setIP( $ip )
					->updateSignalFields( $signalFields );
			}
			catch ( \LogicException $e ) {
				error_log( 'Error updating bot signal with column problem: '.$e->getMessage() );
			}
			catch ( \Exception $e ) {
//					error_log( 'Error updating bot signal: '.$e->getMessage() );
			}
		}
	}

	/**
	 * @return string[]
	 */
	private function getEventsToColumn() :array {
		return \array_map(
			function ( $column ) {
				return \str_replace( '_at', '', $column ).'_at';
			},
			[
				'bottrack_notbot'         => 'notbot',
				'bottrack_altcha'         => 'altcha',
				'frontpage_load'          => 'frontpage',
				'loginpage_load'          => 'loginpage',
				'bottrack_404'            => 'bt404',
				'bottrack_fakewebcrawler' => 'btfake',
				'bottrack_linkcheese'     => 'btcheese',
				'bottrack_loginfailed'    => 'btloginfail',
				'bottrack_useragent'      => 'btua',
				'bottrack_xmlrpc'         => 'btxml',
				'bottrack_logininvalid'   => 'btlogininvalid',
				'bottrack_invalidscript'  => 'btinvalidscript',
				'block_author_fishing'    => 'btauthorfishing',
				'cooldown_fail'           => 'cooldown',
				'request_limit_exceeded'  => 'ratelimit',
				'spam_block_human'        => 'humanspam',
				'comment_markspam'        => 'markspam',
				'comment_unmarkspam'      => 'unmarkspam',
				'firewall_block'          => 'firewall',
				'ip_offense'              => 'offense',
				'ip_blocked'              => 'blocked',
				'ip_unblock'              => 'unblocked',
				'ip_bypass_add'           => 'bypass',
				'login_success'           => 'auth',
			]
		);
	}
}