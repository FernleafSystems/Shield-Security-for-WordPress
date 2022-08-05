<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Used in version 16.0 to migrate the IP List to the newer IP Rules.
 */
class QueueReqDbRecordMigrator extends Shield\Databases\Utility\QueueDbRecordsMigrator {

	public function __construct() {
		parent::__construct( 'db_upgrader_iplists' );
	}

	protected function getNextItems() :array {
		$result = $this->getDbSelector()
					   ->setLimit( static::PAGE_SIZE )
					   ->setPage( 1 )
					   ->query();
		return is_array( $result ) ? $result : [];
	}

	/**
	 * Dispatch
	 *
	 * @access public
	 * @return void
	 */
	public function dispatch() {
		return;
		parent::dispatch();
		error_log( 'dispatch' );
	}

	/**
	 * @return Select
	 */
	protected function getDbSelector() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		return $mod->getDbHandler_IPs()
				   ->getQuerySelector()
				   ->addWhereEquals( 'deleted_at', 0 );
	}

	protected function processRecord( $record ) {
		/** @var $record EntryVO */
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var IpRulesDB\Record $newRecord */
		$newRecord = $mod->getDbH_IPRules()->getRecord();
		$newRecord->applyFromArray( $record->getRawData() );
		unset( $newRecord->id );
		$newRecord->type = $record->list;
		$newRecord->offenses = $record->transgressions;

		if ( strpos( $record->ip, '/' ) ) {
			list( $ip, $cidr ) = explode( '/', $record->ip );
		}
		else {
			$ip = $record->ip;
			$cidr = Services::IP()->getIpVersion( $record->ip ) === 4 ? 32 : 128;
		}
		$newRecord->ip_ref = ( new Shield\Modules\Data\DB\IPs\IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $ip )
			->id;
		$newRecord->cidr = $cidr;

		$success = $mod->getDbH_IPRules()
					   ->getQueryInserter()
					   ->insert( $newRecord );
		$mod->getDbHandler_IPs()
			->getQueryDeleter()
			->setIsSoftDelete()
			->deleteById( $record->id );
		if ( !$success ) {
			throw new \Exception( 'failed to migrate' );
		}

		return $record;
	}
}
