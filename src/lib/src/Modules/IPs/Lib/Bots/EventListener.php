<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class EventListener {

	use ModConsumer;
	use ExecOnce;

	public function fireEventForIP( $ip, $event ) {
		$events = $this->getEventsToColumn();

		foreach ( $events as $eventTrigger => $column ) {
			if ( $eventTrigger === $event || preg_match( sprintf( '#^%s$#', $eventTrigger ), $event ) ) {
				( new UpdateBotField() )
					->setMod( $this->getMod() )
					->setIP( $ip )
					->run( $column );
			}
		}
	}

	protected function canRun() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return !$mod->isVerifiedBot();
	}

	protected function run() {
		add_action( $this->getCon()->prefix( 'event' ), function ( $event ) {
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
				'bottrack_404'            => 'bt404',
				'bottrack_fakewebcrawler' => 'btfake',
				'bottrack_linkcheese'     => 'btcheese',
				'bottrack_loginfailed'    => 'btloginfail',
				'bottrack_useragent'      => 'btua',
				'bottrack_xmlrpc'         => 'btxml',
				'bottrack_logininvalid'   => 'btlogininvalid',
				'bottrack_invalidscript'  => 'btinvalidscript',
				'cooldown_fail'           => 'cooldown',
				'recaptcha_success'       => 'captchapass',
				'recaptcha_fail'          => 'captchafail',
				'spam_block_human'        => 'humanspam',
				'comment_markspam'        => 'markspam',
				'comment_unmarkspam'      => 'unmarkspam',
				'blockparam_.*'           => 'firewall',
				'ip_offense'              => 'offense',
				'ip_blocked'              => 'blocked',
				'ip_unblock'              => 'unblocked',
				'ip_bypass'               => 'bypass',
				'login_success'           => 'auth',
			]
		);
	}
}