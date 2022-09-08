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

		$con = $this->getCon();
		foreach ( $form as $key => $value ) {

			$toEnable = $value === 'Y';

			switch ( $key ) {

				case 'LoginProtectOption':
					$mod = $con->getModule_LoginGuard();
					if ( $toEnable ) { // we don't disable the whole module
						$mod->setIsMainFeatureEnabled( true );
					}
					$mod->getOptions()->setOpt( 'enable_antibot_check', $toEnable ? 'Y' : 'N' );
					break;

				case 'CommentsFilterOption':
					$mod = $this->getCon()->getModule_Comments();
					/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options $optsComm */
					$optsComm = $mod->getOptions();
					if ( $toEnable ) { // we don't disable the whole module
						$mod->setIsMainFeatureEnabled( true );
					}
					$optsComm->setEnabledAntiBot( $toEnable );
					break;

				case 'SecurityPluginBadge':
					$mod = $this->getCon()->getModule_Plugin();
					if ( $toEnable ) { // we don't disable the whole module
						$mod->setIsMainFeatureEnabled( true );
					}
					$mod->getOptions()->setOpt( 'display_plugin_badge', $toEnable ? 'Y' : 'N' );
					$mod->saveModOptions();
					break;

				default:
					throw new \Exception( 'Not a supported option.' );
			}

			$mod->saveModOptions();
		}

		return true;
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
			$this->getStepBuilders()
		);
	}

	private function getStepKeys() :array {
		switch ( $this->workingKey ) {
			case 'guided_setup':
			default:
				$steps = [
					'guided_setup_welcome',
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
	private function getStepBuilders() :array {
		$builders = [];
		foreach ( $this->enumStepBuilders() as $builder ) {
			$builders[ $builder::SLUG ] = $builder;
		}
		$stepKeys = $this->getStepKeys();
		// Extracts and ORDERS all the required Step Builders
		return array_map(
			function ( string $builderClass ) {
				/** @var Steps\Base $builder */
				$builder = new $builderClass();
				return $builder->setMod( $this->getMod() );
			},
			array_merge( array_flip( $stepKeys ), array_intersect_key( $builders, array_flip( $stepKeys ) ) )
		);
	}

	/**
	 * @return Steps\Base[]
	 */
	private function enumStepBuilders() :array {
		return [
			Steps\GuidedSetupWelcome::class,
			Steps\LoginProtection::class,
			Steps\CommentSpam::class,
			Steps\SecurityAdmin::class,
			Steps\SecurityBadge::class,
			Steps\FreeTrial::class,
			Steps\OptIn::class,
			Steps\ThankYou::class,
		];
	}
}