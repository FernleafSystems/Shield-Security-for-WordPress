<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table\Records;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\LoadCrowdsecDecisions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 */
class RecordsLoader extends DynPropertiesClass {

	use ModConsumer;

	const COUNT_SOURCES = 3;

	public function countAll() :int {
		return $this->crowdsecCount() + $this->bypassCount() + $this->blockCount();
	}

	public function loadRecords() :array {
		$records = array_map(
			function ( $record ) {
				// set types and defaults
				$theINTs = [
					'auto_unblock_at',
					'blocked_at',
					'created_at',
					'last_access_at',
				];
				foreach ( $theINTs as $theINT ) {
					$record[ $theINT ] = (int)$record[ $theINT ] ?? 0;
				}

				return $record;
			},
			array_merge(
				$this->crowdsec(),
				$this->bypass(),
				$this->block()
			)
		);
		array_splice( $records, $this->limit );
		return $records;
	}

	private function crowdsec( bool $count = false ) :array {
		$csLoader = ( new LoadCrowdsecDecisions() )
			->setMod( $this->getMod() )
			->applyFromArray( $this->getRawData() );
		$csLoader->limit = $this->limit*self::COUNT_SOURCES;

		return array_map(
			function ( $record ) {
				$data = $record->getRawData();
				$data[ 'ip' ] = $record->ip;
				$data[ 'type' ] = 'CrowdSec';
				$data[ 'offenses' ] = 0;
				$data[ 'blocked_at' ] = $record->created_at;
//				$data[ 'country' ] = empty( $geo->countryCode ) ?
//					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;
				$data[ 'country' ] = 'TODO';
//				$data[ 'last_seen' ] = $this->getColumnContent_LastSeen( $this->record->last_access_at );
//				$data[ 'auto_unblock_at' ] = $this->getColumnContent_UnblockedAt( $this->record->auto_unblock_at );
//				$data[ 'created_since' ] = $this->getColumnContent_Date( $this->record->created_at );
				return $data;
			},
			$count ? $csLoader->countAll() : $csLoader->select()
		);
	}

	private function crowdsecCount() :int {
		return ( new LoadCrowdsecDecisions() )
			->setMod( $this->getMod() )
			->applyFromArray( $this->getRawData() )
			->countAll();
	}

	private function bypass() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
		$sel->filterByWhitelist();
		if ( $this->limit > 0 ) {
			$sel->setLimit( $this->limit*self::COUNT_SOURCES )
				->setPage( round( $this->offset/$this->limit ) );
		}

		return array_map(
			function ( $record ) {
				/** @var  IPs\EntryVO $record */
				$data = $record->getRawData();
				$data[ 'ip' ] = $record->ip;
				$data[ 'type' ] = sprintf( '%s / %s', __( 'Bypass', 'wp-simple-firewall' ), __( 'Whitelist', 'wp-simple-firewall' ) );
				$data[ 'offenses' ] = 0;
				$data[ 'blocked_at' ] = 0;
				$data[ 'auto_unblock_at' ] = 0;
//				$data[ 'country' ] = empty( $geo->countryCode ) ?
//					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;
				$data[ 'country' ] = 'TODO';
				return $data;
			},
			$sel->query()
		);
	}

	private function bypassCount() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
		return $sel->filterByWhitelist()->count();
	}

	protected function block() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
		$sel->filterByBlacklist();
		if ( $this->limit > 0 ) {
			$sel->setLimit( $this->limit*self::COUNT_SOURCES )
				->setPage( round( $this->offset/$this->limit ) );
		}

		return array_map(
			function ( $record ) {
				/** @var  IPs\EntryVO $record */
				$data = $record->getRawData();
				$data[ 'ip' ] = $record->ip;
				$data[ 'type' ] = sprintf( '%s / %s', __( 'Bypass', 'wp-simple-firewall' ), __( 'Whitelist', 'wp-simple-firewall' ) );
				$data[ 'offenses' ] = $record->transgressions;
				$data[ 'auto_unblock_at' ] = 0;
//				$data[ 'country' ] = empty( $geo->countryCode ) ?
//					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;
				$data[ 'country' ] = 'TODO';
				return $data;
			},
			$sel->query()
		);
	}

	private function blockCount() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
		return $sel->filterByBlacklist()->count();
	}
}