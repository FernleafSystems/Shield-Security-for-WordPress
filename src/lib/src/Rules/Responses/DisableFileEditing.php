<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class DisableFileEditing extends Base {

	public const SLUG = 'disable_file_editing';

	public function execResponse() :void {
		add_filter( 'user_has_cap',
			/**
			 * @param array $allCaps
			 * @param array $cap
			 * @param array $args
			 * @return array
			 */
			function ( $allCaps, $cap, $args ) {
				$requestedCapability = $args[ 0 ];
				if ( \in_array( $requestedCapability, [ 'edit_themes', 'edit_plugins', 'edit_files' ] ) ) {
					$allCaps[ $requestedCapability ] = false;
				}
				return $allCaps;
			},
			\PHP_INT_MAX, 3
		);
	}
}