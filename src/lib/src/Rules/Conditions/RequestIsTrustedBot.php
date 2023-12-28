<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class RequestIsTrustedBot extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_is_trusted_bot';

	public function getDescription() :string {
		return __( 'Is the request a bot that originates from a trusted service provider.', 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_trusted_bot;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_trusted_bot = $result;
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestIpIdentities::class,
			'logic'      => EnumLogic::LOGIC_INVERT,
			'params'     => [
				'match_types'  => EnumMatchTypes::MATCH_TYPE_EQUALS,
				'match_ip_ids' => (array)apply_filters( 'shield/untrusted_service_providers', [
					IpID::UNKNOWN,
					IpID::THIS_SERVER,
					IpID::VISITOR,
				] ),
			],
		];
	}
}