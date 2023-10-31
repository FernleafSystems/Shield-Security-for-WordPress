<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\DB\Mfa\Ops;

trait Common {

	public function filterBySlug( string $slug ) {
		return $this->addWhereEquals( 'slug', $slug );
	}

	public function filterByUniqueID( string $uniqueID ) :self {
		return $this->addWhereEquals( 'unique_id', $uniqueID );
	}

	public function filterByUserID( int $ID ) {
		return $this->addWhereEquals( 'user_id', $ID );
	}

	public function filterByUnverified() {
		return $this->addWhereEquals( 'verified_at', 0 );
	}

	public function filterByVerified() {
		return $this->addWhereNewerThan( 0, 'verified_at' );
	}
}