<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class HideGeneratorTag extends Base {

	public const SLUG = 'hide_generator_tag';

	public function execResponse() :bool {
		remove_action( 'wp_head', 'wp_generator' );
		return true;
	}
}