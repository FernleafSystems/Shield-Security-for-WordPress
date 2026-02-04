<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops;

trait Common {

	public function filterByIPRef( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}

	public function filterByBlocked( bool $isBlocked ) :self {
		return $this->addWhere( 'blocked_at', 0, $isBlocked ? '>' : '=' );
	}

	public function filterByCidr( int $cidr ) :self {
		return $this->addWhere( 'cidr', $cidr );
	}

	public function filterByType( string $type ) :self {
		if ( !empty( $type ) ) {
			$this->filterByTypes( [ $type ] );
		}
		return $this;
	}

	public function filterByTypes( array $types ) :self {
		if ( !empty( $types ) ) {
			$this->addWhereIn( 'type', $types );
		}
		return $this;
	}

	public function filterByIsRange( bool $isRange ) :self {
		return $this->addWhereEquals( 'is_range', $isRange ? 1 : 0 );
	}
}