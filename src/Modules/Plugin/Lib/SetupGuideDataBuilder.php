<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MerlinAction,
	Render\Components\SetupGuideBar,
	SetupGuideUpdate
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * The setup guide shown on Shield admin pages until every step has been decided or the guide is closed
 * for good. Minimised it is a bar along the bottom; opened it is a panel over the page. On a new install
 * it opens by itself on the dashboard until it's first minimised. It reuses the welcome wizard's steps,
 * so each step saves exactly as the wizard does. Asked for by link (Configure's "Setup guide"), it opens
 * straight away, even when finished or closed, with every step's form so any of them can be changed.
 */
class SetupGuideDataBuilder {

	use PluginControllerConsumer;

	/**
	 * How long after install the guide still opens by itself. An install older than this, such as a site
	 * updating to a version with the guide, keeps the minimised bar and is never interrupted.
	 */
	private const FIRST_RUN_WINDOW = \WEEK_IN_SECONDS;

	/**
	 * Steps that are only in the guide while they have something to set: the licence step while there's no
	 * licence, and the integrations step while there are integrations waiting to be switched on. The list
	 * of steps therefore follows the licence, as it's activated or lost.
	 */
	private const STEPS_ONLY_WHILE_NEEDED = [
		Steps\License::class,
		Steps\Integrations::class,
	];

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

	/**
	 * The guide for a page as it loads: nothing when every step has been decided or the guide was closed for
	 * good, unless the page's link asked for it. Asking for a closed guide shows it on that page only: the
	 * site's choice stands, as loading a page changes nothing.
	 */
	public function build() :array {
		$isAskedFor = Services::Request()->query( 'setup_guide' ) === 'open';
		if ( !self::con()->isPluginAdmin() || PluginNavs::GetNav() === PluginNavs::NAV_WIZARD ) {
			return [ 'show' => false ];
		}
		$state = new SetupGuideState();
		if ( !$isAskedFor && $state->isDismissed() ) {
			return [ 'show' => false ];
		}

		$guide = $this->buildGuide( $isAskedFor, $isAskedFor ? (string)Services::Request()->query( 'setup_step' ) : '' );
		if ( !$guide[ 'has_steps_to_do' ] && !$isAskedFor ) {
			return [ 'show' => false ];
		}

		$isOnDashboard = PluginNavs::GetNav() === PluginNavs::NAV_DASHBOARD;
		$guide[ 'is_minimised' ] = $state->isMinimised();
		$guide[ 'auto_open' ] = !$isAskedFor && $isOnDashboard && !$guide[ 'is_minimised' ] && $this->isNewInstall();
		// The dashboard is drawn from the settings the guide's steps save, so it's loaded again once they have.
		$guide[ 'reload_on_close' ] = $isOnDashboard;
		return $guide;
	}

	private function isNewInstall() :bool {
		$installedAt = self::con()->comps->opts_lookup->getInstalledAt();
		return $installedAt > 0 && ( Services::Request()->ts() - $installedAt ) < self::FIRST_RUN_WINDOW;
	}

	/**
	 * The guide again, for a page that's already showing it, after settings were saved elsewhere on that page.
	 * It stays on the page even when that save finished setup, so the guide can say so. Who may ask for it is
	 * settled by the action that renders it.
	 * @param bool $withEveryForm whether the page's guide was asked for by link, and so shows every step's form
	 */
	public function buildForRefresh( bool $withEveryForm ) :array {
		return $this->buildGuide( $withEveryForm, '' );
	}

	/**
	 * @param bool   $isAskedFor whether the guide was asked for by link: it opens at once, with every step's form
	 * @param string $askedStep  the step a link asked for, if any
	 */
	private function buildGuide( bool $isAskedFor, string $askedStep ) :array {
		$state = new SetupGuideState();
		$copy = $this->stepCopy();
		// How a step that's been decided stands, in words. A step still to do shows its hint instead.
		$stateLabels = [
			SetupGuideState::STEP_DONE    => __( 'Done', 'wp-simple-firewall' ),
			SetupGuideState::STEP_ALREADY => __( 'Already set up', 'wp-simple-firewall' ),
			SetupGuideState::STEP_SKIPPED => __( 'Skipped', 'wp-simple-firewall' ),
		];
		$steps = [];
		foreach ( self::STEP_HANDLERS as $handlerClass ) {
			$handler = new $handlerClass();
			$isInPlace = $handler->skipStep();
			if ( $isInPlace && \in_array( $handlerClass, self::STEPS_ONLY_WHILE_NEEDED, true ) ) {
				continue;
			}
			$stepState = $state->stepState( $handler::SLUG, $isInPlace );
			$isSetUp = \in_array( $stepState, [ SetupGuideState::STEP_DONE, SetupGuideState::STEP_ALREADY ], true );
			// Steps that are set up need no form, which keeps every page render light, unless the guide was asked for.
			$body = $isSetUp && !$isAskedFor ? '' : $handler->render();
			// A step drawn without its form has a link that opens the guide on that step, form and all, to go over it again.
			$redoHref = $isSetUp && $body === '' ? self::con()->plugin_urls->setupGuide( $handler::SLUG ) : '';
			$steps[] = [
				'slug'      => $handler::SLUG,
				'name'      => $copy[ $handler::SLUG ][ 0 ],
				// What skipping the step is called: its own wording where "skip" doesn't say what it means for the step.
				'skip_label' => $copy[ $handler::SLUG ][ 2 ] ?? __( 'Skip this step', 'wp-simple-firewall' ),
				'can_skip'  => $state->canSkip( $handler::SLUG ),
				'state'     => $stepState,
				'status_label' => $stateLabels[ $stepState ] ?? $copy[ $handler::SLUG ][ 1 ],
				'is_set_up' => $isSetUp,
				'body'      => $body,
				'redo_href' => $redoHref,
				// Changes only when what the step shows does, so a page refreshing its guide can leave alone a
				// step that's the same, along with anything typed into it and not yet saved.
				'version'   => \md5( $stepState.$body.$redoHref ),
			];
		}

		$states = \array_count_values( \array_column( $steps, 'state' ) );
		$doneCount = ( $states[ SetupGuideState::STEP_DONE ] ?? 0 ) + ( $states[ SetupGuideState::STEP_ALREADY ] ?? 0 );
		$skippedCount = $states[ SetupGuideState::STEP_SKIPPED ] ?? 0;
		$next = \current( \array_filter( $steps, static fn( array $step ) :bool => $step[ 'state' ] === SetupGuideState::STEP_TODO ) );

		return [
			'show'            => true,
			'open_now'        => $isAskedFor,
			'auto_open'       => false,
			'is_minimised'    => false,
			'reload_on_close' => false,
			'steps'           => $steps,
			'next_name'       => \is_array( $next ) ? $next[ 'name' ] : '',
			// A link may ask for one step; otherwise the guide opens on the next step to do, or on its summary.
			'open_step'       => \in_array( $askedStep, \array_column( $steps, 'slug' ), true )
				? $askedStep
				: ( \is_array( $next ) ? $next[ 'slug' ] : 'all_done' ),
			// The guide is finished once no step is left undecided: a skipped step has been decided too.
			'has_steps_to_do' => \is_array( $next ),
			'done_count'      => $doneCount,
			'skipped_count'   => $skippedCount,
			'decided_count'   => $doneCount + $skippedCount,
			'total'           => \count( $steps ),
			'tour_href'       => self::con()->plugin_urls->dashboardTour(),
			'merlin_action_json'  => ActionData::BuildJson( MerlinAction::class ),
			'update_action_json'  => ActionData::BuildJson( SetupGuideUpdate::class ),
			'refresh_action_json' => (string)\wp_json_encode( ActionData::BuildAjaxRender( SetupGuideBar::class ) ),
			'strings'     => [
				/* translators: %s: plugin name */
				'title'          => sprintf( __( 'Finish setting up %s', 'wp-simple-firewall' ), self::con()->labels->Name ),
				/* translators: %1$s: steps done, %2$s: total steps */
				'progress'       => __( '%1$s of %2$s done', 'wp-simple-firewall' ),
				/* translators: %s: number of setup steps skipped */
				'skipped_count'  => __( '%s skipped', 'wp-simple-firewall' ),
				'continue'       => __( 'Continue', 'wp-simple-firewall' ),
				'minimise'       => __( 'Minimise', 'wp-simple-firewall' ),
				'close_guide'    => __( 'Close Setup Guide', 'wp-simple-firewall' ),
				'leave_options'  => __( 'More ways to leave the setup guide', 'wp-simple-firewall' ),
				// For a step saved or skipped on the page, which the guide's script marks without a reload.
				'done'           => $stateLabels[ SetupGuideState::STEP_DONE ],
				'skipped'        => $stateLabels[ SetupGuideState::STEP_SKIPPED ],
				'redo'           => __( 'Go over this step again', 'wp-simple-firewall' ),
				'steps_label'    => __( 'Setup steps', 'wp-simple-firewall' ),
				'summary_title'  => __( "What you've set up", 'wp-simple-firewall' ),
				'summary_hint'   => __( 'Review and finish', 'wp-simple-firewall' ),
				'summary_intro'  => __( 'You can go back to any step, or change any of this later in Configure.', 'wp-simple-firewall' ),
				'summary_col_step'   => __( 'Step', 'wp-simple-firewall' ),
				'summary_col_status' => __( 'Status', 'wp-simple-firewall' ),
				'summary_go_back' => __( 'Go back to this step', 'wp-simple-firewall' ),
				'summary_tour'   => __( 'Take a 1-minute tour of the dashboard', 'wp-simple-firewall' ),
				'finish'         => __( 'Finish', 'wp-simple-firewall' ),
				'sidebar_label'  => __( 'Setup guide', 'wp-simple-firewall' ),
				/* translators: %s: name of the next setup step */
				'next'           => __( 'Next: %s', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * Plain-language step names, each with a short hint, written for the guide rather than the
	 * wizard's slide titles. A step may also name what skipping it means.
	 * @return array<string, array{0:string, 1:string, 2?:string}>
	 */
	private function stepCopy() :array {
		return [
			Steps\License::SLUG              => [ __( 'Activate your Pro licence', 'wp-simple-firewall' ), __( "Only if you've bought Pro", 'wp-simple-firewall' ) ],
			Steps\ApplySecurityProfile::SLUG => [ __( 'Choose a protection level', 'wp-simple-firewall' ), __( 'Sets many options at once', 'wp-simple-firewall' ), __( 'Keep my current settings', 'wp-simple-firewall' ) ],
			Steps\SecurityAdmin::SLUG        => [ __( 'Set a Security Admin PIN', 'wp-simple-firewall' ), __( 'Locks your security settings', 'wp-simple-firewall' ) ],
			Steps\ReportEmail::SLUG          => [ __( 'Where should reports go?', 'wp-simple-firewall' ), __( 'Weekly summary and alerts', 'wp-simple-firewall' ) ],
			Steps\Integrations::SLUG         => [ __( 'Protect your site forms', 'wp-simple-firewall' ), __( 'Contact, login and signup forms', 'wp-simple-firewall' ) ],
			Steps\SecurityBadge::SLUG        => [ __( 'Show the Shield badge', 'wp-simple-firewall' ), __( 'Optional', 'wp-simple-firewall' ) ],
		];
	}
}
