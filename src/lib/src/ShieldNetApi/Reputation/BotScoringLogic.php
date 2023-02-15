<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class BotScoringLogic extends Common\BaseShieldNetApi {

	public const API_ACTION = 'bot/antibot_scoring_logic';

	public function retrieve() :?array {
		$raw = $this->sendReq();
		return ( is_array( $raw ) && !empty( $raw[ 'data' ] ) ) ? $raw[ 'data' ] : null;
	}
}