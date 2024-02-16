<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'firewall';

	public function getBlockResponse() :string {
		$response = $this->opts()->getOpt( 'block_response', '' );
		return !empty( $response ) ? $response : 'redirect_die_message'; // TODO: use default
	}
}