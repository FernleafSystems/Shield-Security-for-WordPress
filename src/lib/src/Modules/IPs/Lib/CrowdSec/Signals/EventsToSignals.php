<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Services\Services;

class EventsToSignals extends EventsListener {

	/**
	 * @var array[]
	 */
	private $signals;

	protected function init() {
		$this->signals = [];
	}

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
		if ( $this->isEventCsSignal( $evt ) ) {
			$def = $this->getSignalDef( $evt );
			foreach ( $def[ 'scopes' ] as $scope ) {

				switch ( $scope ) {
					case CrowdSecConstants::SCOPE_IP:
						$value = Services::Request()->ip();
						break;
					default:
						error_log( 'not a supported scope: '.$scope );
						continue( 2 );
				}

				// Certain events should only be sent if the NotBot isn't set for this IP i.e. captcha failure
				$modIPs = $this->getCon()->getModule_IPs();
				if ( !$def[ 'only_send_on_notbot_fail' ]
					 ||
					 ( new BotSignalsRecord() )
						 ->setMod( $modIPs )
						 ->setIP( Services::Request()->ip() )
						 ->retrieve()->notbot_at === 0 ) {

					$signal = [
						'scenario' => $def[ 'scenario' ],
						'scope'    => $scope,
						'value'    => $value,
						'milli_at' => $this->getMilliseconds(),
					];
					// We prevent storing duplicate scenarios using the hash
					$this->signals[ md5( serialize( $signal ) ) ] = $signal;
				}
			}
		}
	}

	protected function onShutdown() {
		if ( !empty( $this->signals ) ) {
			$modIPs = $this->getCon()->getModule_IPs();

			$notBotFail = ( new BotSignalsRecord() )
							  ->setMod( $modIPs )
							  ->setIP( Services::Request()->ip() )
							  ->retrieve()->notbot_at === 0;

			if ( $notBotFail ) {
				$this->signals[] = [
					'scenario' => 'notbotfail',
					'scope'    => CrowdSecConstants::SCOPE_IP,
					'value'    => Services::Request()->ip(),
					'milli_at' => $this->getMilliseconds(),
				];
			}

			$dbhSignals = $modIPs->getDbH_CrowdSecSignals();
			foreach ( $this->signals as $signal ) {
				/** @var CrowdsecSignalsDB\Record $record */
				$dbhSignals->getQueryInserter()
						   ->insert(
							   $dbhSignals->getRecord()->applyFromArray( $signal )
						   );
			}

			// and finally, trigger a send to Crowdsec
			$this->triggerSignalsCron();
		}
	}

	private function getMilliseconds() :string {
		return substr(
			function_exists( 'microtime' ) ? explode( '.', (string)@microtime( true ) )[ 1 ] : '0', 0, 6
		);
	}

	private function triggerSignalsCron() {
		$con = $this->getCon();
		if ( !wp_next_scheduled( $con->prefix( 'adhoc_cron_crowdsec_signals' ) ) ) {
			wp_schedule_single_event(
				Services::Request()->ts() + MINUTE_IN_SECONDS*15,
				$con->prefix( 'adhoc_cron_crowdsec_signals' )
			);
		}
	}

	private function isEventCsSignal( string $evt ) :bool {
		return !empty( $this->getSignalDef( $evt ) );
	}

	private function getSignalDef( string $evt ) :array {
		$def = $this->getEventToSignalMap()[ $evt ] ?? [];
		if ( !empty( $def ) ) {
			// add some defaults
			if ( empty( $def[ 'scopes' ] ) ) {
				$def[ 'scopes' ] = [
					CrowdSecConstants::SCOPE_IP
				];
			}

			if ( !isset( $def[ 'only_send_on_notbot_fail' ] ) ) {
				$def[ 'only_send_on_notbot_fail' ] = false;
			}
		}
		return $def;
	}

	private function getEventToSignalMap() :array {
		return [
			'bottrack_loginfailed'  => [
				'scenario' => 'btloginfail',
			],
			'bottrack_logininvalid' => [
				'scenario' => 'btinvalidscript',
			],
			'block_lostpassword'    => [
				'scenario'                 => 'lostpasswordfail',
				'only_send_on_notbot_fail' => true,
			],
			'block_register'        => [
				'scenario'                 => 'registerfail',
				'only_send_on_notbot_fail' => true,
			],

			'bottrack_404'            => [
				'scenario' => 'bt404',
			],
			'bottrack_fakewebcrawler' => [
				'scenario' => 'btfake',
			],
			'bottrack_linkcheese'     => [
				'scenario' => 'btcheese',
			],
			'bottrack_invalidscript'  => [
				'scenario' => 'btlogininvalid',
			],
			'bottrack_useragent'      => [
				'scenario' => 'btua',
			],
			'bottrack_xmlrpc'         => [
				'scenario' => 'btxml',
			],
			'block_author_fishing'    => [
				'scenario' => 'btauthorfishing',
			],
			'firewall_block'          => [
				'scenario' => 'firewall',
			],
			'ip_offense'              => [
				'scenario' => 'offense',
			],
			'ip_blocked'              => [
				'scenario' => 'blocked',
			],
			'request_limit_exceeded'  => [
				'scenario' => 'ratelimit',
			],
			'spam_block_human'        => [
				'scenario' => 'humanspam',
			],
			'spam_block_recaptcha'    => [
				'scenario' => 'markspam',
			],
			'spam_block_bot'          => [
				'scenario' => 'markspam',
			],
			'spam_block_antibot'      => [
				'scenario' => 'markspam',
			],
			'spam_form_fail'          => [
				'scenario' => 'markspam',
			],
		];
	}
}