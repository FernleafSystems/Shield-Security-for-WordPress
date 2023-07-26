<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield;

class MerlinController {

	use Shield\Modules\PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function processFormSubmit( array $form ) :Shield\Utilities\Response {
		$step = $form[ 'step_slug' ] ?? '';
		if ( empty( $step ) ) {
			throw new \Exception( 'No step configured for this form' );
		}
		$handler = $this->getHandlerFromSlug( $step );
		if ( empty( $handler ) || !\class_exists( $handler ) ) {
			throw new \Exception( 'Invalid Step.' );
		}
		return ( new $handler() )->processStepFormSubmit( $form );
	}

	/**
	 * @throws \Exception
	 */
	public function buildSteps( string $wizardKey ) :array {
		return \array_map(
			function ( $handler ) {
				return [
					'step_slug' => $handler::SLUG,
					'step_name' => $handler->getName(),
					'step_body' => $handler->render(),
				];
			},
			\array_filter(
				$this->getWizardHandlers( $wizardKey ),
				function ( $handler ) {
					return !$handler->skipStep();
				}
			)
		);
	}

	/**
	 * @throws \Exception
	 */
	private function getWizardSteps( string $wizardKey ) :array {
		$constants = ( new \ReflectionClass( Wizards::class ) )->getConstants();
		$fullKey = \strtoupper( 'WIZARD_STEPS_'.$wizardKey );
		if ( !isset( $constants[ $fullKey ] ) ) {
			throw new \Exception( 'Invalid Wizard specified' );
		}
		return $constants[ $fullKey ];
	}

	/**
	 * @return Steps\Base[]
	 * @throws \Exception
	 */
	private function getWizardHandlers( string $wizardKey ) :array {
		return \array_map(
			function ( string $handlerClass ) {
				return new $handlerClass();
			},
			$this->getWizardSteps( $wizardKey )
		);
	}

	/**
	 * @return Steps\Base[]|string|null
	 */
	private function getHandlerFromSlug( string $slug ) :?string {
		$theHandler = null;
		foreach ( $this->getAllHandlers() as $handler ) {
			if ( $handler::SLUG === $slug ) {
				$theHandler = $handler;
				break;
			}
		}
		return $theHandler;
	}

	/**
	 * Simply \array_unique(array_merge()) when more wizards are added.
	 * @return Steps\Base[]|string[]
	 */
	private function getAllHandlers() :array {
		return Wizards::WIZARD_STEPS_WELCOME;
	}
}