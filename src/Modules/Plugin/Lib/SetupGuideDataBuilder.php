<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MerlinAction,
	SetupGuideUpdate
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * The setup guide bar shown along the bottom of Shield admin pages until setup is finished or
 * dismissed. It reuses the welcome wizard's steps, so each step saves exactly as the wizard does.
 * Asked for by link (Configure's "Guided Setup"), it opens straight away, even when finished or
 * dismissed, with every step's form so any of them can be changed.
 */
class SetupGuideDataBuilder {

	use PluginControllerConsumer;

	/**
	 * Wizard steps that make sense inline. The wizard's welcome video, newsletter and thank-you
	 * slides are left to the wizard itself.
	 */
	private const STEP_HANDLERS = [
		Steps\ReportEmail::class,
		Steps\SecurityAdmin::class,
		Steps\License::class,
		Steps\ApplySecurityProfile::class,
		Steps\Integrations::class,
		Steps\SecurityBadge::class,
	];

	/**
	 * @return list<string> the slugs of the guide's steps
	 */
	public static function StepSlugs() :array {
		return \array_map( static fn( string $handlerClass ) :string => $handlerClass::SLUG, self::STEP_HANDLERS );
	}

	public function build() :array {
		$con = self::con();
		$state = new SetupGuideState();
		$isAskedFor = Services::Request()->query( 'setup_guide' ) === 'open';
		if ( !$con->isPluginAdmin() || PluginNavs::GetNav() === PluginNavs::NAV_WIZARD ) {
			return [ 'show' => false ];
		}
		if ( $state->isDismissed() ) {
			if ( !$isAskedFor ) {
				return [ 'show' => false ];
			}
			$state->undismiss();
		}

		$done = $state->doneSlugs();
		$copy = $this->stepCopy();
		$steps = [];
		foreach ( self::STEP_HANDLERS as $handlerClass ) {
			$handler = new $handlerClass();
			if ( $handlerClass === Steps\License::class && $handler->skipStep() ) {
				continue;
			}
			$isDone = $handler->skipStep() || \in_array( $handler::SLUG, $done, true );
			$steps[] = [
				'slug'     => $handler::SLUG,
				'name'     => $copy[ $handler::SLUG ][ 0 ],
				'hint'     => $copy[ $handler::SLUG ][ 1 ],
				'is_done'  => $isDone,
				// Completed steps need no form, which keeps every page render light, unless the guide was asked for.
				'body'     => $isDone && !$isAskedFor ? '' : $handler->render(),
			];
		}

		$doneCount = \count( \array_filter( $steps, static fn( array $step ) :bool => $step[ 'is_done' ] ) );
		if ( $doneCount === \count( $steps ) && !$isAskedFor ) {
			return [ 'show' => false ];
		}
		$next = \current( \array_filter( $steps, static fn( array $step ) :bool => !$step[ 'is_done' ] ) );
		$askedStep = $isAskedFor ? (string)Services::Request()->query( 'setup_step' ) : '';

		return [
			'show'        => true,
			'open_now'    => $isAskedFor,
			'steps'       => $steps,
			'next_name'   => \is_array( $next ) ? $next[ 'name' ] : '',
			// A link may ask for one step; otherwise the guide opens on the next step to do, or says setup is complete.
			'open_step'   => \in_array( $askedStep, \array_column( $steps, 'slug' ), true )
				? $askedStep
				: ( \is_array( $next ) ? $next[ 'slug' ] : 'all_done' ),
			'done_count'  => $doneCount,
			'total'       => \count( $steps ),
			'merlin_action_json' => ActionData::BuildJson( MerlinAction::class ),
			'update_action_json' => ActionData::BuildJson( SetupGuideUpdate::class ),
			'strings'     => [
				'title'          => sprintf( __( 'Finish setting up %s', 'wp-simple-firewall' ), $con->labels->Name ),
				/* translators: %1$s: steps done, %2$s: total steps */
				'progress'       => __( '%1$s of %2$s done', 'wp-simple-firewall' ),
				'continue'       => __( 'Continue', 'wp-simple-firewall' ),
				'collapse'       => __( 'Minimise', 'wp-simple-firewall' ),
				'hide_for_now'   => __( 'Hide until next visit', 'wp-simple-firewall' ),
				'dismiss'        => __( "Don't show again", 'wp-simple-firewall' ),
				'skip'           => __( 'Skip this step', 'wp-simple-firewall' ),
				'done'           => __( 'Done', 'wp-simple-firewall' ),
				'steps_label'    => __( 'Setup steps', 'wp-simple-firewall' ),
				'all_done'       => __( 'Setup is complete. You can change any of this later in Configure.', 'wp-simple-firewall' ),
				'reopen'         => __( 'Setup', 'wp-simple-firewall' ),
				'sidebar_label'  => __( 'Setup guide', 'wp-simple-firewall' ),
				/* translators: %s: name of the next setup step */
				'next'           => __( 'Next: %s', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * Plain-language step names, each with a short hint, written for the guide rather than the
	 * wizard's slide titles.
	 * @return array<string, array{0:string, 1:string}>
	 */
	private function stepCopy() :array {
		return [
			Steps\License::SLUG              => [ __( 'Activate your Pro licence', 'wp-simple-firewall' ), __( 'Unlocks Pro protection', 'wp-simple-firewall' ) ],
			Steps\ApplySecurityProfile::SLUG => [ __( 'Choose a protection level', 'wp-simple-firewall' ), __( 'Sets many options at once', 'wp-simple-firewall' ) ],
			Steps\SecurityAdmin::SLUG        => [ __( 'Set a Security Admin PIN', 'wp-simple-firewall' ), __( 'Stops other admins changing Shield', 'wp-simple-firewall' ) ],
			Steps\ReportEmail::SLUG          => [ __( 'Where should reports go?', 'wp-simple-firewall' ), __( 'Weekly summary and alerts', 'wp-simple-firewall' ) ],
			Steps\Integrations::SLUG         => [ __( 'Protect your site forms', 'wp-simple-firewall' ), __( 'Contact, login and signup forms', 'wp-simple-firewall' ) ],
			Steps\SecurityBadge::SLUG        => [ __( 'Show the Shield badge', 'wp-simple-firewall' ), __( 'Optional', 'wp-simple-firewall' ) ],
		];
	}
}
