<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoStatusProvidedToCheckException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\UnsupportedStatusException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

/**
 * @property string $code
 */
class MatchRequestStatusCode extends Base {

	use Traits\RequestPath;

	public const SLUG = 'match_request_status_code';

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
}