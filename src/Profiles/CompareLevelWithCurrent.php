<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * What applying a protection level would do to the site as it is now: for every setting the levels cover, its
 * value now, its value afterwards, and whether that is stricter or looser. A level only changes the settings it
 * names and that the plan allows, exactly as ApplyProfile does; the rest stay as they are.
 *
 * @phpstan-type ComparisonRow array{
 *   title:string,
 *   now:string,
 *   after:string,
 *   change:'stricter'|'looser'|'same'|'unavailable'
 * }
 * @phpstan-type Comparison array{
 *   groups:list<array{title:string,has_changes:bool,rows:list<ComparisonRow>}>,
 *   stricter:int,
 *   looser:int,
 *   unchanged:int
 * }
 */
class CompareLevelWithCurrent {

	use PluginControllerConsumer;

	private const IN_SECONDS = [ 'login_limit_interval', 'comments_cooldown' ];

	/**
	 * CrowdSec's modes, weakest first. A level only turns it off, or on in the gentler of its two blocking modes,
	 * so a site that blocks outright is loosened by a level that turns CrowdSec "on".
	 */
	private const CROWDSEC_MODES = [ 'disabled', 'block_with_unblock', 'block' ];

	/**
	 * @return Comparison
	 */
	public function build( string $level ) :array {
		$profiles = self::con()->comps->security_profiles;
		$levelValues = [];
		foreach ( $profiles->buildForLevel( $level ) as $sectionKey => $section ) {
			foreach ( $section[ 'opts' ] as $opt ) {
				$levelValues[ $sectionKey ][ $opt[ 'item_key' ] ] = $opt[ 'value' ];
			}
		}

		$comparison = [ 'groups' => [], 'stricter' => 0, 'looser' => 0, 'unchanged' => 0 ];
		foreach ( $profiles->buildForCurrent() as $sectionKey => $section ) {
			$rows = [];
			foreach ( $section[ 'opts' ] as $opt ) {
				$row = $this->buildRow( $opt, $levelValues[ $sectionKey ] ?? [] );
				$rows[] = $row;
				if ( $row[ 'change' ] === 'stricter' || $row[ 'change' ] === 'looser' ) {
					$comparison[ $row[ 'change' ] ]++;
				}
				else {
					$comparison[ 'unchanged' ]++;
				}
			}
			$comparison[ 'groups' ][] = [
				'title'       => $section[ 'title' ],
				'has_changes' => !empty( \array_filter(
					$rows,
					static fn( array $row ) :bool => \in_array( $row[ 'change' ], [ 'stricter', 'looser' ], true )
				) ),
				'rows'        => $rows,
			];
		}
		return $comparison;
	}

	/**
	 * @param array<string,mixed> $opt         one setting, with the site's value for it now
	 * @param array<string,mixed> $levelValues the values the level sets in this setting's group, by item
	 * @return ComparisonRow
	 */
	private function buildRow( array $opt, array $levelValues ) :array {
		if ( !$opt[ 'is_available' ] ) {
			return [ 'title' => $opt[ 'title' ], 'now' => '', 'after' => '', 'change' => 'unavailable' ];
		}

		$optKey = $opt[ 'opt_key' ];
		$isCrowdSec = $optKey === 'cs_block';
		// The site's own CrowdSec mode, as the settings read for a level only say whether it's on.
		$now = $isCrowdSec ? self::con()->opts->optGet( $optKey ) : $this->asComparable( $opt[ 'value' ] );
		$after = $now;
		if ( \array_key_exists( $opt[ 'item_key' ], $levelValues ) ) {
			$levelValue = $levelValues[ $opt[ 'item_key' ] ];
			$after = $isCrowdSec ? ApplyProfile::CrowdSecMode( (bool)$levelValue ) : $this->asComparable( $levelValue );
		}

		$difference = $this->strictness( $optKey, $after ) <=> $this->strictness( $optKey, $now );
		return [
			'title'  => $opt[ 'title' ],
			'now'    => $this->display( $optKey, $now ),
			'after'  => $this->display( $optKey, $after ),
			'change' => $difference === 0 ? 'same' : ( $difference > 0 ? 'stricter' : 'looser' ),
		];
	}

	/**
	 * Levels give a switch as 'Y' or 'N' where the site's own value is read as true or false.
	 * @param mixed $value
	 * @return mixed
	 */
	private function asComparable( $value ) {
		return \in_array( $value, [ 'Y', 'N' ], true ) ? $value === 'Y' : $value;
	}

	/**
	 * @param mixed $value
	 */
	private function strictness( string $optKey, $value ) :float {
		if ( \is_bool( $value ) ) {
			return (float)$value;
		}
		if ( $optKey === 'cs_block' ) {
			return (float)\array_search( $value, self::CROWDSEC_MODES, true );
		}
		if ( $optKey === 'transgression_limit' ) {
			// Fewer offences allowed is stricter, except none at all, which turns automatic blocking off.
			return (int)$value === 0 ? -\INF : -(float)$value;
		}
		$choices = $this->choiceKeys( $optKey );
		if ( !empty( $choices ) ) {
			// A choice's place in its list is how strict it is: an hour, a day, a week.
			return (float)\array_search( $value, $choices, true );
		}
		return (float)$value;
	}

	/**
	 * @param mixed $value
	 */
	private function display( string $optKey, $value ) :string {
		if ( \is_bool( $value ) || ( $optKey === 'transgression_limit' && (int)$value === 0 ) ) {
			return $value ? __( 'On', 'wp-simple-firewall' ) : __( 'Off', 'wp-simple-firewall' );
		}
		foreach ( self::con()->opts->optDef( $optKey )[ 'value_options' ] ?? [] as $choice ) {
			if ( $choice[ 'value_key' ] === $value ) {
				return __( $choice[ 'text' ], 'wp-simple-firewall' );
			}
		}
		// A stored value can be anything, and one that isn't a number or text has nothing to show.
		$value = \is_scalar( $value ) ? (string)$value : '';
		return \in_array( $optKey, self::IN_SECONDS, true )
			/* translators: %s: a number of seconds */
			? sprintf( __( '%s sec', 'wp-simple-firewall' ), $value )
			: $value;
	}

	/**
	 * @return list<string> the choices of a select option, in their own order; empty for any other option
	 */
	private function choiceKeys( string $optKey ) :array {
		$def = self::con()->opts->optDef( $optKey );
		return $def[ 'type' ] === 'select' ? \array_column( $def[ 'value_options' ] ?? [], 'value_key' ) : [];
	}
}
