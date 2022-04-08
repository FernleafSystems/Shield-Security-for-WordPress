<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class BotEventListener extends ExecOnceModConsumer {

	public function fireEventForIP( $ip, $event ) {
		$events = $this->getEventsToColumn();

		foreach ( $events as $eventTrigger => $column ) {
			if ( $eventTrigger === $event ) {
				try {
					( new BotSignalsRecord() )
						->setMod( $this->getMod() )
						->setIP( $ip )
						->updateSignalField( $column );
				}
				catch ( \LogicException $e ) {
					error_log( 'Error updating bot signal with column problem: '.$e->getMessage() );
				}
				catch ( \Exception $e ) {
//					error_log( 'Error updating bot signal: '.$e->getMessage() );
				}
			}
		}
	}

	protected function canRun() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return !$this->getCon()->this_req->is_trusted_bot && $mod->getDbH_BotSignal()->isReady();
	}

	protected function run() {
		add_action( 'shield/event', function ( $event ) {
			$this->fireEventForIP( Services::IP()->getRequestIp(), $event );
		} );
	}

	/**
	 * @return string[]
	 */
	private function getEventsToColumn() :array {
		return array_map(
			function ( $column ) {
				return str_replace( '_at', '', $column ).'_at';
			},
			[
				'bottrack_notbot'         => 'notbot',
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
				'recaptcha_success'       => 'captchapass',
				'request_limit_exceeded'  => 'ratelimit',
				'recaptcha_fail'          => 'captchafail',
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