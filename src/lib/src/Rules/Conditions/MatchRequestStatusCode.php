<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoStatusProvidedToCheckException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\UnsupportedStatusException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $code
 */
class MatchRequestStatusCode extends Base {

	const SLUG = 'match_request_status_code';

	protected function execConditionCheck() :bool {
		if ( empty( $this->code ) ) {
			throw new NoStatusProvidedToCheckException( 'No status parameter provided to check' );
		}

		switch ( $this->code ) {
			case '404':
				$match = is_404();
				$this->addConditionTriggerMeta( 'path', Services::Request()->getPath() );
				break;
			default:
				throw new UnsupportedStatusException( 'Status parameter provided is not supported: '.$this->code );
		}

		return $match;
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::TEMPLATE_REDIRECT;
	}
}