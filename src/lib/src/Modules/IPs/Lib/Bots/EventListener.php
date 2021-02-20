<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class EventListener {

	use ModConsumer;
	use ExecOnce;

	protected function run() {
		add_action( $this->getCon()->prefix( 'event' ), function ( $eventTag ) {
			$events = $this->getEventsToColumn();
			if ( in_array( $eventTag, array_keys( $events ) ) ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();

				$IP = $this->getVisitorEntry();
				$IP->{$events[ $eventTag ]} = Services::Request()->ts();

				if ( empty( $IP->id ) ) {
					$mod->getDbHandler_BotSignals()
						->getQueryInserter()
						->insert( $IP );
				}
				else {
					$mod->getDbHandler_BotSignals()
						->getQueryUpdater()
						->updateEntry( $IP, $IP->getRawData() );
				}
			}
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
				'bottrack_404'            => 'bt404',
				'bottrack_fakewebcrawler' => 'btfake',
				'bottrack_linkcheese'     => 'btcheese',
				'bottrack_loginfailed'    => 'btloginfail',
				'bottrack_useragent'      => 'btua',
				'bottrack_xmlrpc'         => 'btxml',
				'bottrack_logininvalid'   => 'btlogininvalid',
				'bottrack_invalidscript'  => 'btinvalidscript',
				'ip_offense'              => 'offense',
				'ip_blocked'              => 'blocked',
				'ip_unblock'              => 'unblocked',
				'ip_bypass'               => 'bypass',
				'login_success'           => 'auth',
			]
		);
	}

	private function getVisitorEntry() :EntryVO {
		try {
			$entry = ( new RetrieveIpBotRecord() )
				->setMod( $this->getMod() )
				->current();
		}
		catch ( \Exception $e ) {
			$entry = new EntryVO();
			$entry->ip = Services::IP()->getRequestIp();
		}
		return $entry;
	}
}