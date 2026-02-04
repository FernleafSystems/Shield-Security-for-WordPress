<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\Ops;

trait Common {

	public function filterByIPRef( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}

	public function filterByUser( int $userID ) {
		return $this->addWhereEquals( 'user_id', $userID );
	}

	public function filterByHardSuspended() {
		return $this->addWhereNewerThan( 0, 'hard_suspended_at' );
	}

	public function filterByPassExpired( int $expiresAt ) {
		return $this->addWhereOlderThan( $expiresAt, 'pass_started_at' );
	}

	public function filterByIdle( int $expiresAt ) {
		return $this->addWhereOlderThan( $expiresAt, 'first_seen_at' )
					->addWhereOlderThan( $expiresAt, 'last_login_at' )
					->addWhereOlderThan( $expiresAt, 'pass_started_at' );
	}
}