<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP\TabManageSitePage;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\{
	ShieldLoginIntentPage,
	WpReplicaLoginIntentPage
};

class FullPageDisplayDynamic extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_dynamic';

	public static function allowedRenderSlugs() :array {
		return [
			ShieldLoginIntentPage::SLUG,
			WpReplicaLoginIntentPage::SLUG,
			TabManageSitePage::SLUG,
		];
	}
}
