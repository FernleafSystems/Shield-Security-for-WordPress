<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class AjaxRender extends BaseAction {

	use SecurityAdminNotRequired;

	public const SLUG = 'ajax_render';

	protected function exec() {
		$response = $this->getCon()
						 ->getModule_Insights()
						 ->getActionRouter()
						 ->action(
							 $this->action_data[ 'render_slug' ],
							 $this->getParamsMinusAjax()
						 );
		foreach ( [ 'success', 'message', 'error' ] as $item ) {
			if ( isset( $response->action_response_data[ $item ] ) ) {
				$response->{$item} = $response->action_response_data[ $item ];
			}
		}

		$this->setResponse( $response );
	}

	protected function getParamsMinusAjax() :array {
		return array_diff_key(
			$this->action_data,
			array_flip( [
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