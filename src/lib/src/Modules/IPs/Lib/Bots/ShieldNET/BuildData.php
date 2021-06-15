<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildData {

	use ModConsumer;

	public function build() :array {
		return array_filter( array_map(
			function ( $entryVO ) {
				$data = [
					'ip'      => $entryVO->ip,
					'signals' => [],
				];
				foreach ( $entryVO->getRawData() as $col => $value ) {
					if ( strpos( $col, '_at' ) && $value > 0
						 && !in_array( $col, [ 'updated_at', 'created_at', 'deleted_at' ] ) ) {
						$data[ 'signals' ][] = str_replace( '_at', '', $col );
					}
				}
				return empty( $data[ 'signals' ] ) ? [] : $data;
			},
			$this->getRecords()
		) );
	}

	/**
	 * @return EntryVO[]
	 */
	private function getRecords() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQuerySelector();
		$records = $select->setLimit( 100 )
						  ->setOrderBy( 'updated_at', 'DESC' )
						  ->query();
		return is_array( $records ) ? $records : [];
	}
}