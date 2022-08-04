<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table\Records;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPGeoVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\CrowdSecRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\LoadCrowdsecDecisions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\CleanDecisions_IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\CrowdSec\ForCrowdsecDecisions;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 */
class RecordsLoader extends DynPropertiesClass {

	use ModConsumer;

	public function countAll() :int {
		return count( array_merge(
			$this->crowdsec(),
			$this->bypass(),
			$this->block()
		) );
	}

	public function loadRecords() :array {
		return array_map(
			function ( $record ) {
				// set types and defaults
				$theINTs = [
					'created_at',
					'last_access_at',
				];
				foreach ( $theINTs as $theINT ) {
					$record[ 'created_at' ] = (int)$record[ $theINT ] ?? 0;
				}

				return $record;
			},
			array_merge(
				$this->crowdsec(),
				$this->bypass(),
				$this->block()
			)
		);
	}

	private function crowdsec() :array {
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
			( new LoadCrowdsecDecisions() )
				->setMod( $this->getMod() )
				->applyFromArray( $this->getRawData() )
				->select()
		);
	}

	protected function bypass() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
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
			$sel->filterByWhitelist()->query()
		);
	}

	protected function block() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Select $sel */
		$sel = $mod->getDbHandler_IPs()->getQuerySelector();
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
			$sel->filterByBlacklist()->query()
		);
	}
}