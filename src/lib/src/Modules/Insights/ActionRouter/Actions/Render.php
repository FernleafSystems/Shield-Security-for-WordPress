<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Services\Services;

/**
 * This is the entry point for ALL rendering. This is the action that should be called, which will then delegate
 * the rendering further onward. This will allow us to customization the rendering data and environment for all
 * renders before they're ever processed.
 */
class Render extends BaseAction {

	use AuthNotRequired;

	public const SLUG = 'render';
	public const PRIMARY_MOD = 'insights';

	protected function exec() {
		$req = Services::Request();
		$this->setResponse(
			$this->getCon()
				 ->getModule_Insights()
				 ->getActionRouter()
				 ->action(
					 $this->action_data[ 'render_action_slug' ],
					 Services::DataManipulation()->mergeArraysRecursive(
						 $req->query,
						 $req->post,
						 array_filter( $this->action_data[ 'render_action_data' ] ?? [], function ( $item ) {
							 return !is_null( $item );
						 } )
					 )
				 )
		);
	}

	protected function getRequiredDataKeys() :array {
		return [
			'render_action_slug',
			'render_action_data'
		];
	}
}