<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

abstract class Base {

	use Shield\Modules\ModConsumer;

	const SLUG = '';

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
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( Actions\Render\Components\Merlin\MerlinStep::SLUG, $stepData );
	}

	/**
	 * @throws \Exception
	 */
	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$resp = new Shield\Utilities\Response();
		$resp->success = false;
		$resp->error = 'No form processing has been configured for this step';
		$resp->addData( 'page_reload', false );
		return $resp;
	}

	protected function getStepRenderData() :array {
		return [];
	}
}