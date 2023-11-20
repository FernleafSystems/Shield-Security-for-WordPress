<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'firewall';

	public function getBlockResponse() :string {
		$response = $this->opts()->getOpt( 'block_response', '' );
		return !empty( $response ) ? $response : 'redirect_die_message'; // TODO: use default
	}
}