<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IpMeta\Ops;

/**
 * @deprecated 19.1
 */
trait Common {

	public function filterByIPRef( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}
}