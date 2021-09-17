<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal\Ops;

trait Common {

	public function filterByIP( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}
}