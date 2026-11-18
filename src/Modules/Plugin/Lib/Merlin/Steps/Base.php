<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

abstract class Base {

	use PluginControllerConsumer;

	public const SLUG = '';

	public function getName() :string {
		return 'Title Unset';
	}

	public function skipStep() :bool {
		return false;
	}

	public function render() :string {
		$stepData = $this->getStepRenderData();
		if ( !isset( $stepData[ 'vars' ] ) ) {
			$stepData[ 'vars' ] = [];
		}
		$stepData[ 'vars' ][ 'step_slug' ] = static::SLUG;
		return self::con()->action_router->render( Actions\Render\Components\Merlin\MerlinStep::class, $stepData );
	}

	/**
	 * @param array<array-key,string> $form the step's form: every field is text (see MerlinController)
	 * @throws \Exception
	 */
	public function processStepFormSubmit( array $form ) :Response {
		$resp = new Response();
		$resp->success = false;
		$resp->error = __( 'No form processing has been configured for this step', 'wp-simple-firewall' );
		$resp->addData( 'page_reload', false );
		return $resp;
	}

	protected function getStepRenderData() :array {
		return [];
	}
}