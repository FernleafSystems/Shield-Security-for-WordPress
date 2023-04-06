<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield;

class MerlinController {

	use Shield\Modules\PluginControllerConsumer;

	private $workingKey;

	/**
	 * @throws \Exception
	 */
	public function processFormSubmit( array $form ) :Shield\Utilities\Response {
		$step = $form[ 'step_slug' ] ?? '';
		if ( empty( $step ) ) {
			throw new \Exception( 'No step configured for this form' );
		}
		$handlers = $this->getStepHandlers( false );
		if ( !isset( $handlers[ $step ] ) ) {
			throw new \Exception( 'Invalid Step.' );
		}
		return $handlers[ $step ]->processStepFormSubmit( $form );
	}

	public function buildSteps( string $key ) :array {
		$this->workingKey = $key;
		return array_map(
			function ( $handler ) {
				return [
					'step_slug' => $handler::SLUG,
					'step_name' => $handler->getName(),
					'step_body' => $handler->render(),
				];
			},
			array_filter( $this->getStepHandlers( true ), function ( $handler ) {
				return !$handler->skipStep();
			} )
		);
	}

	private function getStepKeys() :array {
		switch ( $this->workingKey ) {
			case 'guided_setup':
			default:
				$steps = [
					'guided_setup_welcome',
					'license',
					'ip_detect',
					'security_admin',
					'ip_blocking',
					'login_protection',
					'comment_spam',
					'security_badge',
					'free_trial',
					'opt_in',
					'thank_you',
				];
				break;
		}
		return $steps;
	}

	/**
	 * @return Steps\Base[]
	 */
	private function getStepHandlers( bool $filterByStepKeys ) :array {
		$stepKeys = array_flip( $this->getStepKeys() );

		// Extracts and ORDERS all the required Step Handlers
		$handlers = $this->enumStepHandlers();
		if ( $filterByStepKeys ) {
			$handlers = array_filter(
				array_merge( $stepKeys, array_intersect_key( $handlers, $stepKeys ) ),
				function ( $handler ) {
					return !is_numeric( $handler );
				}
			);
		}

		return array_map(
			function ( string $handlerClass ) {
				return new $handlerClass();
			},
			$handlers
		);
	}

	/**
	 * @return Steps\Base[]
	 */
	private function enumStepHandlers() :array {
		$classes = [
			Steps\GuidedSetupWelcome::class,
			Steps\Import::class,
			Steps\IpDetect::class,
			Steps\IpBlocking::class,
			Steps\LoginProtection::class,
			Steps\CommentSpam::class,
			Steps\SecurityAdmin::class,
			Steps\SecurityBadge::class,
			Steps\FreeTrial::class,
			Steps\License::class,
			Steps\OptIn::class,
			Steps\ThankYou::class,
		];

		$handlers = [];
		foreach ( $classes as $class ) {
			$handlers[ $class::SLUG ] = $class;
		}

		return $handlers;
	}
}