<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\{
	EntryVO,
	Select
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;

/**
 * Class Retrieve
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops
 * @deprecated 11.2
 */
class Retrieve {

	use ModConsumer;

	/**
	 * @param string $ip
	 * @return EntryVO|null
	 */
	public function byIP( string $ip ) {
		return $this->getSelector()->filterByIp( $ip )->first();
	}

	public function byUsername( string $username ) :bool {
		return $this->getSelector()->filterByUsername( $username )->first();
	}

	private function getSelector() :Select {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_Sessions()->getQuerySelector();
	}
}