<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\FullPageDisplay;

class DisplayBlockPage extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_block';

	protected function getSuccessCode() :int {
		return 503;
	}
}