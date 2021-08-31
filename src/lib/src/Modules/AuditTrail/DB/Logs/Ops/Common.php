<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs\Ops;

trait Common {

	public function filterByIP( string $ip ) {
		return $this->addWhereEquals( 'ip', inet_pton( $ip ) );
	}
}