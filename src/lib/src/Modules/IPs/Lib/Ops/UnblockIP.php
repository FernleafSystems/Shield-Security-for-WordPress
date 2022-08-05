<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Services\Services;

class UnblockIP {

	use Shield\Modules\ModConsumer;
	use IPs\Components\IpAddressConsumer;

	public function run() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IpRulesDB\Update $updater */
		$updater = $mod->getDbH_IPRules()->getQueryUpdater();

		$ipRecord = ( new LookupIP() )
			->setMod( $mod )
			->setIP( $this->getIP() )
			->setIsIpBlocked( true )
			->lookup( false );
		return !empty( $ipRecord ) &&
			   $updater->updateById( $ipRecord->id, [
				   'offenses'     => 0,
				   'unblocked_at' => Services::Request()->ts(),
			   ] );
	}
}