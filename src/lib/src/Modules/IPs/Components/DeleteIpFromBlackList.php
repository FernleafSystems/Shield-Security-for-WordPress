<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class DeleteIpFromBlackList
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class DeleteIpFromBlackList {

	use Shield\Databases\Base\HandlerConsumer;

	/**
	 * TODO: Do we need the initial lookup?
	 * @param string $sIp
	 * @return bool
	 */
	public function run( $sIp ) {
		$oIP = null;
		if ( !empty( $sIp ) && Services::IP()->isViablePublicVisitorIp( $sIp ) ) {
			$oIP = ( new LookupIpOnList() )
				->setDbHandler( $this->getDbHandler() )
				->setListTypeBlack()
				->setIp( $sIp )
				->lookup( false );
		}
		return ( $oIP instanceof IPs\EntryVO ) && $this->getDbHandler()
													   ->getQueryDeleter()
													   ->deleteEntry( $oIP );
	}
}