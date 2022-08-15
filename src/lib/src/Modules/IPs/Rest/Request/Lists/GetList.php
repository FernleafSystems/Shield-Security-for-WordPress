<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;

class GetList extends Base {

	protected function process() :array {
		$req = $this->getRequestVO();

		switch ( $req->list ) {
			case 'crowdsec':
				$types = [ Handler::T_CROWDSEC ];
				break;
			case 'bypass':
			case 'white':
				$types = [ Handler::T_MANUAL_WHITE ];
				break;
			case 'black':
			case 'block':
				$types = [ Handler::T_AUTO_BLACK, Handler::T_MANUAL_BLACK ];
			default:
				break;
		}

		$loader = ( new LoadIpRules() )->setMod( $this->getMod() );
		$loader->wheres = [
			sprintf( "`ir`.`type` IN ('%s')", implode( "','", $types ) )
		];

		return array_map(
			function ( $record ) {
				return $this->convertIpRuleToArray( $record );
			},
			$loader->select()
		);
	}
}