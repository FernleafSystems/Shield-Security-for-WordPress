<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta\Ops;

trait Common {

	public function filterByMetaKey( string $key ) {
		return $this->addWhereEquals( 'meta_key', $key );
	}
}