<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class MerlinController {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return PluginNavs::GetNav() === PluginNavs::NAV_WIZARD;
	}

	protected function run() {
		add_filter( 'shield/custom_enqueue_assets', fn( $assets ) => \array_merge( \is_array( $assets ) ? $assets : [], [ 'shield/tp/vimeo_player' ] ) );
	}

	/**
	 * A wizard form is text fields only, and it's request input. One that holds anything else isn't a form a
	 * wizard page sends, so it's refused here, once, and each step takes its fields as the text they are.
	 * The refusal says nothing of what was sent: field names are request input too, and the message is shown.
	 * @param array $form the submitted form, as it arrived
	 * @throws \Exception
	 */
	public function processFormSubmit( array $form ) :Response {
		if ( $form !== \array_filter( $form, '\is_string' ) ) {
			throw new ActionException( __( 'This form could not be read.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: MerlinAction returns it in an AJAX response, which the page shows as text.
		}

		$step = $form[ 'step_slug' ] ?? '';
		if ( $step === '' ) {
			throw new \Exception( __( 'No step configured for this form', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: MerlinAction returns it in an AJAX response, which the page shows as text.
		}
		$handler = $this->getHandlerFromSlug( $step );
		if ( empty( $handler ) || !\class_exists( $handler ) ) {
			throw new \Exception( __( 'Invalid Step.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: MerlinAction returns it in an AJAX response, which the page shows as text.
		}
		return ( new $handler() )->processStepFormSubmit( $form );
	}

	/**
	 * @throws \Exception
	 */
	public function buildSteps( string $wizardKey ) :array {
		return \array_map(
			fn( $handler ) => [
				'step_slug' => $handler::SLUG,
				'step_name' => $handler->getName(),
				'step_body' => $handler->render(),
			],
			\array_filter(
				$this->getWizardHandlers( $wizardKey ),
				fn( $handler ) => !$handler->skipStep()
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
		return \array_map( fn( string $handlerClass ) => new $handlerClass(), $this->getWizardSteps( $wizardKey ) );
	}

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
	 * @return class-string<Steps\Base>[]
	 */
	private function getAllHandlers() :array {
		return Wizards::WIZARD_STEPS_WELCOME;
	}
}
