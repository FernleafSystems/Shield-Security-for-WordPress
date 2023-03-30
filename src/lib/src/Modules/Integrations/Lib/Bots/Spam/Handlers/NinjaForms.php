<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\Helpers\NinjaForms_ShieldSpamAction;

/**
 * A rather convoluted way to integrate. First you must add your custom "action" to the list
 * of actions to be executed on a submission.
 *
 * Then you must create a Custom Action class which will handle the action and add it to the
 * registered action.
 *
 * Unfortunately the action register is executed early and so hooking to Init breaks it.
 */
class NinjaForms extends Base {

	protected function run() {

		add_filter( 'ninja_forms_register_actions', function ( $actions ) {
			$actions[ 'shieldantibot' ] = ( new NinjaForms_ShieldSpamAction() )->setHandler( $this );
			return $actions;
		}, 1000 );

		add_filter( 'ninja_forms_submission_actions', function ( $actions ) {
			$actions[] = [
				'id'       => 'shieldantibot',
				'settings' => [
					'active' => true,
					'type'   => 'shieldantibot',
				]
			];
			return $actions;
		}, 1000 );
	}
}