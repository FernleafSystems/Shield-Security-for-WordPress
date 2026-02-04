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

		/**
		 * Ninja forms fires the filter very early in the load, which is cracker stuff, so we must manually add our
		 * action to the list, taking care that public access to the `actions` array is maintained going forward.
		 */
		if ( did_action( 'plugins_loaded' ) && $this->canAddDirectlyToActionsProperty() ) {
			\Ninja_Forms::instance()->actions = $this->registerShieldAction( \Ninja_Forms::instance()->actions );
		}
		else {
			add_filter( 'ninja_forms_register_actions', function ( $actions ) {
				return \is_array( $actions ) ? $this->registerShieldAction( $actions ) : $actions;
			}, 1000 );
		}

		add_filter( 'ninja_forms_submission_actions', function ( $actions ) {
			if ( \is_array( $actions ) ) {
				$actions[] = [
					'id'       => 'shieldantibot',
					'settings' => [
						'active' => true,
						'type'   => 'shieldantibot',
					]
				];
			}
			return $actions;
		}, 1000 );
	}

	private function registerShieldAction( array $actions ) :array {
		$actions[ 'shieldantibot' ] = ( new NinjaForms_ShieldSpamAction() )->setHandler( $this );
		return $actions;
	}

	/**
	 * Checks that the method is there and that there is a properties named "actions" on that class, and that it's
	 * public
	 */
	private function canAddDirectlyToActionsProperty() :bool {
		$can = false;
		if ( \method_exists( \Ninja_Forms::class, 'instance' ) ) {
			try {
				$can = ( new \ReflectionClass( \Ninja_Forms::class ) )->getProperty( 'actions' )->isPublic();
			}
			catch ( \Exception $e ) {
			}
		}
		return $can;
	}
}