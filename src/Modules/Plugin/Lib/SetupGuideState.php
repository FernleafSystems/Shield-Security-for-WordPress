<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * The site's progress through the setup guide: steps saved, steps skipped, whether the guide was minimised,
 * and whether it was closed for good. Setup belongs to the plugin, so every admin sees the same progress.
 */
class SetupGuideState {

	use PluginControllerConsumer;

	public const OPT_KEY = 'setup_guide_state';

	/** Nothing has been decided for the step yet. */
	public const STEP_TODO = 'todo';
	/** The step was saved from the guide or the wizard. */
	public const STEP_DONE = 'done';
	/** The step's setting was in place without the step being saved. */
	public const STEP_ALREADY = 'already';
	/** The step was skipped: decided, but not set up. */
	public const STEP_SKIPPED = 'skipped';

	/**
	 * The licence step is only in the guide while there's no licence, so a record of it having been saved can't
	 * mean it's done. Such a record reads as skipped: the step has been dealt with, and can be set up from there.
	 */
	private const NEVER_DONE_BY_RECORD = [
		Steps\License::SLUG,
	];

	/**
	 * Steps that can't be skipped: where reports go has to be chosen. Such a step is only ever done by its
	 * setting being in place, whatever was recorded for it.
	 */
	private const MUST_BE_SET = [
		Steps\ReportEmail::SLUG,
	];

	public function canSkip( string $stepSlug ) :bool {
		return !\in_array( $stepSlug, self::MUST_BE_SET, true );
	}

	public function isDismissed() :bool {
		return !empty( $this->stored()[ 'dismissed_at' ] );
	}

	public function dismiss() :void {
		$this->store( \array_merge( $this->stored(), [ 'dismissed_at' => Services::Request()->ts() ] ) );
	}

	public function isMinimised() :bool {
		return !empty( $this->stored()[ 'minimised' ] );
	}

	public function minimise() :void {
		$this->store( \array_merge( $this->stored(), [ 'minimised' => true ] ) );
	}

	/**
	 * @return list<string>
	 */
	public function doneSlugs() :array {
		return $this->slugsIn( 'done' );
	}

	/**
	 * @return list<string>
	 */
	public function skippedSlugs() :array {
		return $this->slugsIn( 'skipped' );
	}

	/**
	 * Where a step stands, from what was recorded for it and whether its setting is in place now.
	 * @param bool $isInPlace whether the step's setting is already as the step would leave it
	 */
	public function stepState( string $stepSlug, bool $isInPlace ) :string {
		if ( !$isInPlace && !$this->canSkip( $stepSlug ) ) {
			return self::STEP_TODO;
		}
		$isRecordedDone = \in_array( $stepSlug, $this->doneSlugs(), true );
		if ( $isRecordedDone && !\in_array( $stepSlug, self::NEVER_DONE_BY_RECORD, true ) ) {
			return self::STEP_DONE;
		}
		if ( $isInPlace ) {
			return self::STEP_ALREADY;
		}
		return ( $isRecordedDone || \in_array( $stepSlug, $this->skippedSlugs(), true ) ) ? self::STEP_SKIPPED : self::STEP_TODO;
	}

	/**
	 * Only the guide's own steps are recorded. The wizard's other steps, and anything else a request names, are not.
	 */
	public function markDone( string $stepSlug ) :bool {
		return $this->record( $stepSlug, 'done', 'skipped' );
	}

	/**
	 * A skipped step stays skipped until it's saved. Skipping a step that was saved changes nothing, and a
	 * step that can't be skipped isn't.
	 */
	public function markSkipped( string $stepSlug ) :bool {
		if ( !$this->canSkip( $stepSlug ) ) {
			return false;
		}
		if ( \in_array( $stepSlug, $this->doneSlugs(), true ) ) {
			return \in_array( $stepSlug, SetupGuideDataBuilder::StepSlugs(), true );
		}
		return $this->record( $stepSlug, 'skipped', 'done' );
	}

	/**
	 * @param string $into the list the step joins
	 * @param string $from the list the step leaves, as a step is in one or the other
	 */
	private function record( string $stepSlug, string $into, string $from ) :bool {
		if ( !\in_array( $stepSlug, SetupGuideDataBuilder::StepSlugs(), true ) ) {
			return false;
		}
		$state = $this->stored();
		$state[ $into ] = \array_values( \array_unique( \array_merge( $this->slugsIn( $into ), [ $stepSlug ] ) ) );
		$state[ $from ] = \array_values( \array_diff( $this->slugsIn( $from ), [ $stepSlug ] ) );
		if ( empty( $state[ $from ] ) ) {
			unset( $state[ $from ] );
		}
		$this->store( $state );
		return true;
	}

	/**
	 * @return list<string>
	 */
	private function slugsIn( string $list ) :array {
		$slugs = $this->stored()[ $list ] ?? [];
		return \is_array( $slugs ) ? \array_values( \array_filter( $slugs, 'is_string' ) ) : [];
	}

	private function stored() :array {
		return self::con()->opts->optGet( self::OPT_KEY );
	}

	private function store( array $state ) :void {
		self::con()->opts->optSet( self::OPT_KEY, $state );
	}
}
