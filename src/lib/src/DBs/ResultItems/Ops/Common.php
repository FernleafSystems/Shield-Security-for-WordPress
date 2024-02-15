<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

trait Common {

	public function filterByNoRepairAttempted() {
		return $this->addWhereEquals( 'attempt_repair_at', 0 );
	}

	public function filterByNotIgnored() {
		return $this->addWhereEquals( 'ignored_at', 0 );
	}

	public function filterByItemNotRepaired() {
		return $this->addWhereEquals( 'item_repaired_at', 0 );
	}

	public function filterByItemNotDeleted() {
		return $this->addWhereEquals( 'item_deleted_at', 0 );
	}

	public function filterByIgnored() {
		return $this->addWhereNewerThan( 0, 'ignored_at' );
	}

	public function filterByItemID( string $itemID ) {
		return $this->addWhereEquals( 'item_id', $itemID );
	}

	public function filterByItemType( string $type ) {
		return $this->addWhereEquals( 'item_type', $type );
	}

	public function filterByNotified() {
		return $this->addWhereNewerThan( 0, 'notified_at' );
	}

	public function filterByNotNotified() {
		return $this->addWhereNewerThan( 0, 'notified_at' );
	}

	public function filterByTypeFile() {
		return $this->filterByItemType( Handler::ITEM_TYPE_FILE );
	}

	public function filterByTypePlugin() {
		return $this->filterByItemType( Handler::ITEM_TYPE_PLUGIN );
	}

	public function filterByTypeTheme() {
		return $this->filterByItemType( Handler::ITEM_TYPE_THEME );
	}
}