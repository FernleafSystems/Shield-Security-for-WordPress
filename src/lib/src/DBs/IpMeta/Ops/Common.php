<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta\Ops;

trait Common {

	public function filterByIPRef( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}
}