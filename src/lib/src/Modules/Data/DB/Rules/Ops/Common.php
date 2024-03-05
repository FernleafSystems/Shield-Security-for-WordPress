<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops;

/**
 * @deprecated 19.1
 */
trait Common {

	public function filterByActive() {
		return $this->addWhereEquals( 'is_active', 1 );
	}

	public function filterByType( string $type ) {
		return $this->addWhereEquals( 'type', $type );
	}

	public function filterByUser( int $userID ) {
		return $this->addWhereEquals( 'user_id', $userID );
	}

	public function filterByInactive() {
		return $this->addWhereEquals( 'is_active', 0 );
	}

	public function filterByEarlyDraft() {
		return $this->filterByInactive()
					->addRawWhere( [ '`form`', 'IS', 'NULL' ] );
	}
}