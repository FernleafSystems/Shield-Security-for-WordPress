<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoStatusProvidedToCheckException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\UnsupportedStatusException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

/**
 * @property string $code
 * @deprecated 18.6
 */
class MatchRequestStatusCode extends Base {

	use Traits\TypeRequest;
	use Traits\RequestPath;

	public const SLUG = 'match_request_status_code';

	public function getDescription() :string {
		return __( 'Does the request response status code match the given code.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( !isset( $this->code ) ) {
			throw new NoStatusProvidedToCheckException( 'No status parameter provided to check' );
		}

		switch ( $this->code ) {
			case '404':
				$match = is_404();
				$this->addConditionTriggerMeta( 'path', $this->getRequestPath() );
				break;
			default:
				throw new UnsupportedStatusException( 'Status parameter provided is not supported: '.$this->code );
		}

		return $match;
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::TEMPLATE_REDIRECT;
	}

	public function getParamsDef() :array {
		return [
			'code' => [
				'type'  => EnumParameters::TYPE_INT,
				'label' => __( 'Match Response Status Code', 'wp-simple-firewall' ),
			],
		];
	}
}