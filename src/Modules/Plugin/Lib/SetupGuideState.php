<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * The site's progress through the setup guide: steps completed or skipped, and whether the guide
 * was dismissed for good. Setup belongs to the plugin, so every admin sees the same progress.
 * Steps whose setting is already in place count as done regardless.
 */
class SetupGuideState {

	use PluginControllerConsumer;

	public const OPT_KEY = 'setup_guide_state';

	public function isDismissed() :bool {
		return !empty( $this->stored()[ 'dismissed_at' ] );
	}

	public function dismiss() :void {
		$this->store( \array_merge( $this->stored(), [ 'dismissed_at' => Services::Request()->ts() ] ) );
	}

	/**
	 * @return list<string>
	 */
	public function doneSlugs() :array {
		$done = $this->stored()[ 'done' ] ?? [];
		return \is_array( $done ) ? \array_values( \array_filter( $done, 'is_string' ) ) : [];
	}

	/**
	 * Only the guide's own steps are recorded. The wizard's other steps, and anything else a request names, are not.
	 */
	public function markDone( string $stepSlug ) :bool {
		if ( !\in_array( $stepSlug, SetupGuideDataBuilder::StepSlugs(), true ) ) {
			return false;
		}
		$state = $this->stored();
		$state[ 'done' ] = \array_values( \array_unique( \array_merge( $this->doneSlugs(), [ $stepSlug ] ) ) );
		$this->store( $state );
		return true;
	}

	private function stored() :array {
		return self::con()->opts->optGet( self::OPT_KEY );
	}

	private function store( array $state ) :void {
		self::con()->opts->optSet( self::OPT_KEY, $state );
	}
}
