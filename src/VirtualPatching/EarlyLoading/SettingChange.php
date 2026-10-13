<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The last change made to the early loading setting, stored apart from Shield's options. A request that saves
 * Shield's options writes all of them, so one that began before the change (a long WP-Cron run, for example)
 * can save the old value back. This record keeps what was actually chosen.
 */
final class SettingChange {

	public function __construct(
		public readonly int $at,
		public readonly ?bool $enabled
	) {
	}

	/**
	 * Reads the stored option, which may be missing or damaged.
	 */
	public static function fromOption( mixed $value ) :self {
		return \is_array( $value ) ?
			new self( (int)( $value[ 'at' ] ?? 0 ), \is_bool( $value[ 'enabled' ] ?? null ) ? $value[ 'enabled' ] : null )
			: new self( 0, null );
	}

	/**
	 * @return array{at:int,enabled:?bool}
	 */
	public function toOption() :array {
		return [
			'at'      => $this->at,
			'enabled' => $this->enabled,
		];
	}

	public function madeSince( int $time ) :bool {
		return $this->at >= $time;
	}

	/**
	 * Whether the stored setting is on only because an older copy of the options was saved over the change
	 * that switched it off. The other way round only leaves early loading off, and can't be told apart from
	 * the licence lapsing, so it isn't corrected.
	 */
	public function revertedBy( bool $enabled ) :bool {
		return $enabled && $this->enabled === false;
	}
}
