<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Services\Services;

class EventsToSignals extends EventsListener {

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

				$this->storeScenario( $def[ 'scenario' ], $scope, $value );
				$this->triggerSignalsCron();
			}
		}
	}

	private function storeScenario( string $scenario, string $scope, string $value ) {
		$dbhSignals = $this->getCon()->getModule_IPs()->getDbH_CrowdSecSignals();
		/** @var CrowdsecSignalsDB\Record $record */
		$record = $dbhSignals->getRecord();
		$record->scenario = $scenario;
		$record->scope = $scope;
		$record->value = $value;
		$dbhSignals->getQueryInserter()->insert( $record );
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
		}
		return $def;
	}

	private function getEventToSignalMap() :array {
		return [
			'bottrack_loginfailed'    => [
				'scenario' => 'btloginfail',
			],
			'bottrack_logininvalid'   => [
				'scenario' => 'btinvalidscript',
			],
			'block_lostpassword'   => [
				'scenario' => 'btlostpassword',
			],
			'block_register'   => [
				'scenario' => 'btregister',
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