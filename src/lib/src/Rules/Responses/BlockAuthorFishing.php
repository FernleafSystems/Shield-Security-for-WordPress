<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class BlockAuthorFishing extends Base {

	const SLUG = 'block_author_fishing';

	protected function execResponse() :bool {
		$this->getCon()
			 ->getModule_Insights()
			 ->getActionRouter()
			 ->action( Actions\FullPageDisplay\DisplayBlockPage::SLUG, [
				 'render_slug' => Actions\Render\FullPage\Block\BlockAuthorFishing::SLUG
			 ] );
		return true;
	}
}