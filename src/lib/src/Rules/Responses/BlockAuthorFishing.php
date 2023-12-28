<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

/**
 * @deprecated 18.5.8
 */
class BlockAuthorFishing extends Base {

	public const SLUG = 'block_author_fishing';

	public function execResponse() :void {
		self::con()->action_router->action( Actions\FullPageDisplay\DisplayBlockPage::class, [
			'render_slug' => Actions\Render\FullPage\Block\BlockAuthorFishing::SLUG
		] );
	}
}