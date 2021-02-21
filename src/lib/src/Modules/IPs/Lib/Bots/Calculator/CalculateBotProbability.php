<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\RetrieveIpBotRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CalculateBotProbability {

	use IpAddressConsumer;
	use ModConsumer;

	/**
	 * @var EntryVO
	 */
	private $entry = null;

	private $scores = [];

	public function calculate() :int {
		$this->entry = $this->loadEntry();

		$this->scores = ( new BuildScores() )
			->setEntryVO( $this->entry )
			->build();

		$score = array_sum( $this->getActiveScores() );

		return (int)max( 0, $score );
	}

	private function getActiveScores() :array {
		return array_filter(
			$this->scores,
			function ( $score ) {
				return $score !== -1;
			}
		);
	}

	private function getAllFields( $filterForMethods = false ) :array {
		$fields = array_map(
			function ( $col ) {
				return str_replace( '_at', '', $col );
			},
			array_filter(
				array_keys( $this->entry->getRawData() ),
				function ( $col ) {
					return preg_match( '#_at$#', $col ) &&
						   !in_array( $col, [ 'updated_at', 'created_at', 'deleted_at' ] );
				}
			)
		);

		if ( $filterForMethods ) {
			$fields = array_filter( $fields, function ( $field ) {
				return method_exists( $this, 'score_'.$field );
			} );
		}

		return $fields;
	}

	private function loadEntry() :EntryVO {
		$ip = $this->getIP();
		if ( empty( $ip ) ) {
			$ip = Services::IP()->getRequestIp();
		}
		try {
			$ip = $this->getIP();
			$entry = ( new RetrieveIpBotRecord() )
				->setMod( $this->getMod() )
				->forIP( $ip );
		}
		catch ( \Exception $e ) {
			$entry = new EntryVO();
			$entry->ip = $ip;
		}
		$this->entry = $entry;
		return $entry;
	}
}