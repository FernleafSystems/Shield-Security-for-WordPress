<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class AjaxRender extends BaseAction {

	use AnyUserAuthRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'ajax_render';

	protected function exec() {
		$routedResponse = self::con()->action_router->action(
			$this->action_data[ 'render_slug' ],
			$this->getParamsMinusAjax()
		);
		$payload = $routedResponse->payload();
		$response = $routedResponse->actionResponse();
		foreach ( [ 'success', 'message', 'error' ] as $item ) {
			if ( isset( $payload[ $item ] ) ) {
				$response->{$item} = $payload[ $item ];
			}
		}

		$this->setResponse( $routedResponse );
	}

	protected function getParamsMinusAjax() :array {
		return \array_diff_key(
			$this->action_data,
			\array_flip( [
				ActionData::FIELD_ACTION,
				ActionData::FIELD_EXECUTE,
				ActionData::FIELD_NONCE,
				ActionData::FIELD_WRAP_RESPONSE,
				'render_slug'
			] )
		);
	}

	protected function getRequiredDataKeys() :array {
		return [
			'render_slug'
		];
	}
}
