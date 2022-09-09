<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield;

class MerlinController {

	use Shield\Modules\ModConsumer;

	private $workingKey;

	public function render( string $key ) :string {
		$this->workingKey = $key;
		return $this->getMod()->renderTemplate(
			'/components/merlin/container.twig',
			[
				'content' => [
					'steps' => $this->buildSteps()
				],
				'vars'    => [
					'step_keys' => $this->getStepKeys()
				],
			]
		);
	}

	/**
	 * @throws \Exception
	 */
	public function processFormSubmit( array $form ) :bool {
		return $this->getStepBuilders( false )[ $form[ 'step_slug' ] ]->processStepFormSubmit( $form );
	}

	private function buildSteps() :array {
		return array_map(
			function ( $builder ) {
				return [
					'step_slug' => $builder::SLUG,
					'step_name' => $builder->getName(),
					'step_body' => $builder->render(),
				];
			},
			array_filter( $this->getStepBuilders( true ), function ( $builder ) {
				return !$builder->skipStep();
			} )
		);
	}

	private function getStepKeys() :array {
		switch ( $this->workingKey ) {
			case 'guided_setup':
			default:
				$steps = [
					'guided_setup_welcome',
					'security_admin',
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
	private function getStepBuilders( bool $filterByStepKeys ) :array {
		$stepKeys = $this->getStepKeys();
		// Extracts and ORDERS all the required Step Builders
		return array_map(
			function ( string $builderClass ) {
				/** @var Steps\Base $builder */
				$builder = new $builderClass();
				return $builder->setMod( $this->getMod() );
			},
			$filterByStepKeys ?
				array_merge( array_flip( $stepKeys ), array_intersect_key( $this->enumStepBuilders(), array_flip( $stepKeys ) ) )
				: $this->enumStepBuilders()
		);
	}

	/**
	 * @return Steps\Base[]
	 */
	private function enumStepBuilders() :array {
		$classes = [
			Steps\GuidedSetupWelcome::class,
			Steps\LoginProtection::class,
			Steps\CommentSpam::class,
			Steps\SecurityAdmin::class,
			Steps\SecurityBadge::class,
			Steps\FreeTrial::class,
			Steps\OptIn::class,
			Steps\ThankYou::class,
		];

		$builders = [];
		foreach ( $classes as $class ) {
			$builders[ $class::SLUG ] = $class;
		}

		return $builders;
	}
}