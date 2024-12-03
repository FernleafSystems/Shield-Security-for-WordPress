<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ApplyProfile {

	use PluginControllerConsumer;

	private array $profile;

	public function __construct( array $profile ) {
		$this->profile = $profile;
	}

	public function run() :void {
		$con = self::con();
		$opts = $con->opts;

		foreach ( $this->profile as $section ) {
			foreach ( $section[ 'opts' ] as $opt ) {
				$optKey = $opt[ 'opt_key' ];
				$optValue = $opt[ 'value' ];
				if ( $opts->optHasAccess( $optKey ) ) {

					if ( \is_bool( $optValue ) ) {
						if ( $opts->optDef( $optKey )[ 'type' ] === 'multiple_select' ) {
							$current = $opts->optGet( $optKey );
							$opts->optSet( $optKey,
								\array_unique( $optValue ? \array_merge( $current, [ $opt[ 'item_key' ] ] ) : \array_diff( $current, [ $opt[ 'item_key' ] ] ) )
							);
						}
						/* Special Cases */
						elseif ( $optKey === 'cs_block' ) {
							$opts->optSet( 'cs_block', $optValue ? 'block_with_unblock' : 'disabled' );
						}
					}
					else {
						$opts->optSet( $optKey, $optValue );
					}
				}
			}
		}
	}
}