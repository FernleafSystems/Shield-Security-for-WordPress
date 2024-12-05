<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ProfileFromConfig {

	use PluginControllerConsumer;

	public function build() :array {
		$con = self::con();
		$opts = $con->opts;

		$structure = $con->comps->security_profiles->getStructure();

		foreach ( $structure as &$section ) {
			foreach ( $section[ 'opts' ] as &$opt ) {
				$optKey = $opt[ 'opt_key' ];
				if ( $opts->optHasAccess( $optKey ) ) {

					$current = $opts->optGet( $optKey );
					$type = $opts->optDef( $optKey )[ 'type' ];

					if ( $type === 'multiple_select' ) {
						$opt[ 'value' ] = \in_array( $opt[ 'item_key' ], $current );
					}
					elseif ( $type === 'checkbox' ) {
						$opt[ 'value' ] = $current === 'Y';
					}
					/* Special Cases */
					elseif ( $optKey === 'cs_block' ) {
						$opt[ 'value' ] = \in_array( $current, [ 'block_with_unblock', 'block' ] );
					}
					else {
						$opt[ 'value' ] = $current;
					}
				}
			}
		}

		return $structure;
	}
}