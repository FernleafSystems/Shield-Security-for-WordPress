<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Per-user display preferences for admin screens that offer more than one layout.
 * Only known keys and their listed values are stored; the first value is the default.
 * The Reports trend chart also remembers the period and events last charted.
 */
class UiPreferences {

	use PluginControllerConsumer;

	// Renamed when the component list became the default, so earlier choices start again from it.
	public const CONFIGURE_ZONE_LAYOUT = 'configure_layout';
	public const ACTIONS_QUEUE_VIEW = 'actions_queue_view';
	public const REPORTS_TRENDS = 'reports_trends';

	private const CHOICES = [
		self::CONFIGURE_ZONE_LAYOUT => [ 'list', 'expand' ],
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
		if ( !\in_array( $value, self::CHOICES[ $key ] ?? [], true ) ) {
			return false;
		}
		return $this->store( $key, $value );
	}

	/**
	 * @return array{period:string,events:list<string>}|null
	 */
	public function getReportsTrends() :?array {
		$value = $this->stored()[ self::REPORTS_TRENDS ] ?? null;
		return \is_array( $value ) && \is_string( $value[ 'period' ] ?? null ) && \is_array( $value[ 'events' ] ?? null )
			? [
				'period' => $value[ 'period' ],
				'events' => \array_values( \array_filter( $value[ 'events' ], '\is_string' ) ),
			]
			: null;
	}

	/**
	 * @param list<string> $events
	 */
	public function setReportsTrends( string $period, array $events ) :bool {
		return $this->store( self::REPORTS_TRENDS, [
			'period' => $period,
			'events' => \array_values( $events ),
		] );
	}

	/**
	 * @param string|array $value
	 */
	private function store( string $key, $value ) :bool {
		$meta = self::con()->user_metas->current();
		if ( empty( $meta ) ) {
			return false;
		}
		$prefs = \array_intersect_key( $this->stored(), self::CHOICES + [ self::REPORTS_TRENDS => true ] );
		$prefs[ $key ] = $value;
		$meta->ui_prefs = $prefs;
		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function stored() :array {
		$meta = self::con()->user_metas->current();
		return ( !empty( $meta ) && \is_array( $meta->ui_prefs ) ) ? $meta->ui_prefs : [];
	}
}
