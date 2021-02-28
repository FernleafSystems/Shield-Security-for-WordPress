<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers\Helpers\NinjaForms_ShieldSpamAction;

/**
 * A rather convoluted way to integrate. First you must add your custom "action" to the list
 * of actions to be executed on a submission.
 *
 * Then you must create a Custom Action class which will handle the action and add it to the
 * registered action.
 *
 * Class NinjaForms
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers
 */
class NinjaForms extends Base {

	protected function run() {

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

		add_filter( 'ninja_forms_register_actions', function ( $actions ) {
			$actions[ 'shieldantibot' ] = ( new NinjaForms_ShieldSpamAction() )
				->setHandler( $this );
			return $actions;
		}, 1000 );
	}

	protected function getProviderName() :string {
		return 'Ninja Forms';
	}

	protected function isPluginInstalled() :bool {
		return @class_exists( '\Ninja_Forms' );
	}
}