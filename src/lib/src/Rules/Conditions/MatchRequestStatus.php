<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoStatusProvidedToCheckException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\UnsupportedStatusException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $status
 */
class MatchRequestStatus extends Base {

	const SLUG = 'match_request_status';

	protected function execConditionCheck() :bool {
		if ( empty( $this->status ) ) {
			throw new NoStatusProvidedToCheckException( 'No status parameter provided to check' );
		}

		switch ( $this->status ) {
			case '404':
				$match = is_404();
				$this->addConditionTriggerMeta( 'path', Services::Request()->getPath() );
				break;
			default:
				throw new UnsupportedStatusException( 'Status parameter provided is not supported: '.$this->status );
		}

		return $match;
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::TEMPLATE_REDIRECT;
	}
}