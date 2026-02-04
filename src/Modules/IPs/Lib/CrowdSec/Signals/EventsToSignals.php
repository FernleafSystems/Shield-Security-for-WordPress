<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Services\Services;

class EventsToSignals extends \FernleafSystems\Wordpress\Plugin\Shield\Events\EventsListener {

	/**
	 * @var array[]
	 */
	private array $signals;

	/**
	 * @var ?int
	 */
	private ?int $capturedStatusCode = null;

	protected function init() {
		$this->signals = [];

		/**
		 * This could be a global service.
		 */
		add_filter( 'status_header', function ( $status_header = null, $code = null ) {
			if ( $code !== null ) {
				$this->capturedStatusCode = (int)$code;
			}
			return $status_header;
		}, \PHP_INT_MAX, 2 );
	}

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
		$signalDef = $this->getSignalDef( $evt );
		if ( !empty( $signalDef ) ) {
			foreach ( $signalDef[ 'scopes' ] as $scope ) {

				switch ( $scope ) {
					case CrowdSecConstants::SCOPE_IP:
						$value = Services::Request()->ip();
						break;
					default:
						error_log( 'not a supported scope: '.$scope );
						continue( 2 );
				}

				// Certain events should only be sent if the NotBot isn't set for this IP i.e. captcha failure
				try {
					if ( !$signalDef[ 'only_send_on_notbot_fail' ]
						 ||
						 ( new BotSignalsRecord() )
							 ->setIP( Services::Request()->ip() )
							 ->retrieve()->notbot_at === 0 ) {

						$signal = [
							'scenario' => $signalDef[ 'scenario' ],
							'scope'    => $scope,
							'value'    => $value,
							'milli_at' => $this->getMilliseconds(),
						];
						// We prevent storing duplicate scenarios using the hash
						$this->signals[ \hash( 'md5', \serialize( $signal ) ) ] = $signal;
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}

	protected function onShutdown() {
		if ( $this->isCommit() && !empty( $this->signals ) ) {
			$con = self::con();
			try {
				if ( ( new BotSignalsRecord() )->setIP( Services::Request()->ip() )->retrieve()->notbot_at === 0 ) {
					$this->signals[] = [
						'scenario' => 'notbotfail',
						'scope'    => CrowdSecConstants::SCOPE_IP,
						'value'    => Services::Request()->ip(),
						'milli_at' => $this->getMilliseconds(),
					];
				}
			}
			catch ( \Exception $e ) {
			}

			$dbhSignals = $con->db_con->crowdsec_signals;
			foreach ( $this->signals as $signal ) {
				/** @var CrowdsecSignalsDB\Record $record */
				$record = $dbhSignals->getRecord()->applyFromArray( $signal );

				$metaData = [
					'context' => [
						'method'     => \strtoupper( $con->this_req->method ),
						'target_uri' => $con->this_req->path,
						'user_agent' => $con->this_req->useragent,
						'status'     => $this->capturedStatusCode === null ? (int)http_response_code() : $this->capturedStatusCode,
					],
				];
				if ( \method_exists( $record, 'arrayDataWrap' ) && !empty( $record->arrayDataWrap( $metaData ) ) ) {
					$record->meta = $metaData;
					$dbhSignals->getQueryInserter()->insert( $record );
				}
			}

			$con->comps->crowdsec->scheduleSignalsPushCron();
		}
	}

	private function getMilliseconds() :string {
		$milli = '0';
		if ( \function_exists( 'microtime' ) ) {
			$ts = microtime();
			if ( !empty( $ts ) && \strpos( $ts, ' ' ) ) {
				$ts = \explode( ' ', $ts )[ 0 ];
				if ( \strpos( $ts, '.' ) ) {
					$milli = \rtrim( \substr( \explode( '.', $ts )[ 1 ] ?? '', 0, 6 ), '0' );
				}
			}
		}
		return \strlen( $milli ) > 0 ? $milli : '0';
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
			'bottrack_loginfailed'    => [
				'scenario' => 'btloginfail',
			],
			'bottrack_logininvalid'   => [
				'scenario' => 'btlogininvalid',
			],
			'block_lostpassword'      => [
				'scenario'                 => 'lostpasswordfail',
				'only_send_on_notbot_fail' => true,
			],
			'block_register'          => [
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
				'scenario' => 'btinvalidscript',
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

	/**
	 * @deprecated 20.1
	 */
	private function triggerSignalsCron() :void {
	}
}