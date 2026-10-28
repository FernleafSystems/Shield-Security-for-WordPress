<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Per-user display preferences for admin screens that offer more than one layout.
 * Only known keys and their listed values are stored; the first value is the default.
 */
class UiPreferences {

	use PluginControllerConsumer;

	public const CONFIGURE_ZONE_LAYOUT = 'configure_zone_layout';
	public const ACTIONS_QUEUE_VIEW = 'actions_queue_view';

	private const CHOICES = [
		self::CONFIGURE_ZONE_LAYOUT => [ 'expand', 'list' ],
		self::ACTIONS_QUEUE_VIEW    => [ 'sections', 'tabs' ],
	];

	public function get( string $key ) :string {
		$choices = self::CHOICES[ $key ] ?? [];
		if ( empty( $choices ) ) {
			return '';
		}
		$value = $this->stored()[ $key ] ?? '';
		return \in_array( $value, $choices, true ) ? $value : $choices[ 0 ];
	}

	public function set( string $key, string $value ) :bool {
		$meta = self::con()->user_metas->current();
		if ( empty( $meta ) || !\in_array( $value, self::CHOICES[ $key ] ?? [], true ) ) {
			return false;
		}
		$prefs = \array_intersect_key( $this->stored(), self::CHOICES );
		$prefs[ $key ] = $value;
		$meta->ui_prefs = $prefs;
		return true;
	}

	/**
	 * @return array<string,string>
	 */
	private function stored() :array {
		$meta = self::con()->user_metas->current();
		return ( !empty( $meta ) && \is_array( $meta->ui_prefs ) ) ? $meta->ui_prefs : [];
	}
}
