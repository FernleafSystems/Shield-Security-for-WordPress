<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

class BotScoringLogic extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'bot/antibot_scoring_logic';

	public function retrieve() :?array {
		$raw = $this->sendReq();
		return ( \is_array( $raw ) && !empty( $raw[ 'data' ] ) ) ? $raw[ 'data' ] : null;
	}
}