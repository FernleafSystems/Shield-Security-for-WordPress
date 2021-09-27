<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops;

trait Common {

	public function filterByScan( int $scanID ) {
		return $this->addWhereEquals( 'scan_ref', $scanID );
	}

	public function filterByNotIgnored() {
		return $this->addWhereEquals( 'ignored_at', 0 );
	}

	public function filterByItemHash( string $hash ) {
		return $this->addWhereEquals( 'hash', $hash );
	}

	public function filterByItemID( string $itemID ) {
		return $this->addWhereEquals( 'item_id', $itemID );
	}

	public function filterByItemType( string $type ) {
		return $this->addWhereEquals( 'item_type', $type );
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