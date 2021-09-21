<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops;

trait Common {

	public function filterByIP( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}

	public function filterByReqID( string $reqID ) :self {
		return $this->addWhereEquals( 'req_id', $reqID );
	}
}