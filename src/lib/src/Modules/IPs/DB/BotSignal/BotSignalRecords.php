<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalRecords {

	use ModConsumer;
	use IpAddressConsumer;

	public function loadRecord( bool $autoCreate = true ) :Ops\Record {

		$ip = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $this->getIP(), true );

		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Ops\Select $select */
		$select = $mod->getDbH_BotSignal()->getQuerySelector();
		/** @var Ops\Record|null $record */
		$record = $select->filterByIP( $ip->id )->first();

		if ( empty( $record ) && $autoCreate && $this->addReq( $ip->id ) ) {
			$record = $this->loadRecord( $reqID, $ipRefID, false );
		}

		return $record;
	}

	public function addReq( int $ipRef ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_BotSignal();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		$record = new Ops\Record();
		$record->ip_ref = $ipRef;
		return $insert->insert( $record );
	}

	/**
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return Services::WpDb()->selectCustom(
			sprintf( "SELECT ips.ip, bs.*,
						FROM `%s` as bs
						INNER JOIN `%s` as ips
							ON ips.id = req.ip_ref 
							AND `ips`.`ip`=INET6_ATON('%s')
						LIMIT 1;",
				$mod->getDbH_BotSignal()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				empty( $this->getIP() ) ? '' : sprintf( "AND ips.ip=INET6_ATON('%s')", $this->getIP() )
			)
		);
	}
}