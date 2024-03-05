<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
class RuleRecords {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function byID( int $ID ) :?Ops\Record {
		$record = $this->getSelector()->byId( $ID );
		if ( !$record instanceof Ops\Record ) {
			throw new \Exception( 'No such record ID' );
		}
		return $record;
	}

	public function deleteOldDrafts( int $old = MINUTE_IN_SECONDS*5 ) :void {
		Services::WpDb()->doSql( sprintf( 'DELETE FROM `%s` WHERE `form` IS NULL AND `updated_at`<%s;',
			self::con()->db_con->dbhRules()->getTableSchema()->table,
			Services::Request()->ts() - $old
		) );
	}

	public function disableAll() :void {
		Services::WpDb()->doSql( sprintf( 'UPDATE `%s` SET `is_active`=0, `updated_at`=%s WHERE `is_active`=1;',
			self::con()->db_con->dbhRules()->getTableSchema()->table,
			Services::Request()->ts()
		) );
	}

	public function getLatestFirstDraft() :?Ops\Record {
		$this->deleteOldDrafts();
		return self::con()->caps->canCustomSecurityRules() ? $this->getSelector()->filterByEarlyDraft()->first() : null;
	}

	/**
	 * @return Ops\Record[]
	 */
	public function getActiveCustom() :array {
		return $this->getCustom( true );
	}

	/**
	 * @return Ops\Record[]
	 */
	public function getCustom( ?bool $active = null ) :array {
		$records = [];
		if ( self::con()->caps->canCustomSecurityRules() ) {
			$dbh = self::con()->db_con->dbhRules();
			$selector = $this->getSelector()->filterByType( $dbh::TYPE_CUSTOM );
			if ( $active !== null ) {
				$active ? $selector->filterByActive() : $selector->filterByInactive();
			}
			$selector->setOrderBy( 'exec_order', 'ASC', true )
					 ->setOrderBy( 'id', 'ASC' );
			$records = $selector->queryWithResult();
		}
		return \is_array( $records ) ? $records : [];
	}

	private function getSelector() {
		return self::con()->db_con->dbhRules()->getQuerySelector();
	}
}