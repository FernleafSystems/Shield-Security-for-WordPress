<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Per-user progress through the setup guide: steps completed or skipped, and whether the guide
 * was dismissed for good. Steps whose setting is already in place count as done regardless.
 */
class SetupGuideState {

	use PluginControllerConsumer;

	public function isDismissed() :bool {
		return !empty( $this->stored()[ 'dismissed_at' ] );
	}

	public function undismiss() :bool {
		$state = $this->stored();
		unset( $state[ 'dismissed_at' ] );
		return $this->store( $state );
	}

	public function dismiss() :bool {
		return $this->store( \array_merge( $this->stored(), [ 'dismissed_at' => Services::Request()->ts() ] ) );
	}

	/**
	 * @return list<string>
	 */
	public function doneSlugs() :array {
		$done = $this->stored()[ 'done' ] ?? [];
		return \is_array( $done ) ? \array_values( \array_filter( $done, 'is_string' ) ) : [];
	}

	public function markDone( string $stepSlug ) :bool {
		$stepSlug = sanitize_key( $stepSlug );
		if ( $stepSlug === '' ) {
			return false;
		}
		$state = $this->stored();
		$state[ 'done' ] = \array_values( \array_unique( \array_merge( $this->doneSlugs(), [ $stepSlug ] ) ) );
		return $this->store( $state );
	}

	private function stored() :array {
		$meta = self::con()->user_metas->current();
		return ( !empty( $meta ) && \is_array( $meta->setup_guide ) ) ? $meta->setup_guide : [];
	}

	private function store( array $state ) :bool {
		$meta = self::con()->user_metas->current();
		if ( empty( $meta ) ) {
			return false;
		}
		$meta->setup_guide = $state;
		return true;
	}
}
