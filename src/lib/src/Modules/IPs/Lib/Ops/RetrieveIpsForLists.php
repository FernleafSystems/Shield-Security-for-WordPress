<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

class RetrieveIpsForLists {

	use HandlerConsumer;

	/**
	 * @return string[]
	 */
	public function all() :array {
		return $this->forLists();
	}

	/**
	 * @return string[]
	 */
	public function white() :array {
		return $this->forLists( [ ModCon::LIST_MANUAL_WHITE ] );
	}

	/**
	 * @return string[]
	 */
	public function black() :array {
		return $this->forLists( [ ModCon::LIST_AUTO_BLACK, ModCon::LIST_MANUAL_BLACK ] );
	}

	/**
	 * @return string[]
	 */
	public function blackAuto() :array {
		return $this->forLists( [ ModCon::LIST_AUTO_BLACK ] );
	}

	/**
	 * @return string[]
	 */
	public function blackManual() :array {
		return $this->forLists( [ ModCon::LIST_MANUAL_BLACK ] );
	}

	/**
	 * @return string[]
	 */
	private function forLists( array $lists = [] ) :array {
		$result = [];
		/** @var IPs\Select $selector */
		$selector = $this->getDbHandler()
						 ->getQuerySelector()
						 ->addColumnToSelect( 'ip' )
						 ->setIsDistinct( true );
		if ( !empty( $lists ) ) {
			$selector->filterByLists( $lists );
		}
		$distinct = $selector->query();
		if ( is_array( $distinct ) ) {
			$result = IpListSort::Sort( $distinct );
		}
		return $result;
	}
}
