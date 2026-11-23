<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops;

trait Common {

	public function filterByIPRef( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
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
}