<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;

/**
 * @deprecated 16.0
 */
class DeleteIP {

	use Shield\Modules\ModConsumer;
	use IPs\Components\IpAddressConsumer;

	public function fromBlacklist() :bool {
		$this->getCon()->fireEvent( 'ip_unblock', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		return $this->getDeleter()
					->filterByTypes( [ Handler::T_AUTO_BLACK, Handler::T_MANUAL_BLACK ] )
					->query();
	}

	public function fromWhiteList() :bool {
		$this->getCon()->fireEvent( 'ip_bypass_remove', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		return $this->getDeleter()
					->filterByType( Handler::T_MANUAL_WHITE )
					->query();
	}

	private function getDeleter() :IpRulesDB\Delete {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IpRulesDB\Delete $deleter */
		$deleter = $mod->getDbH_IPRules()->getQueryDeleter();
		$IPRecord = ( new Shield\Modules\Data\DB\IPs\IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $this->getIP() );
		return $deleter->filterByIPRef( $IPRecord->id )->setLimit( 1 );
	}
}