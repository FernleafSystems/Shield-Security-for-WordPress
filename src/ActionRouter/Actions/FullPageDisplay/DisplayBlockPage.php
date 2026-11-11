<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\{
	BlockAuthorFishing,
	BlockFirewall,
	BlockIpAddressCrowdsec,
	BlockIpAddressShield,
	BlockPageSiteBlockdown,
	BlockTrafficRateLimitExceeded
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;

class DisplayBlockPage extends BaseFullPageDisplay {

	use ByPassIpBlock;

	public const SLUG = 'display_full_page_block';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function getSuccessCode() :int {
		return 503;
	}

	public static function allowedRenderSlugs() :array {
		return [
			BlockIpAddressShield::SLUG,
			BlockIpAddressCrowdsec::SLUG,
			BlockFirewall::SLUG,
			BlockAuthorFishing::SLUG,
			BlockPageSiteBlockdown::SLUG,
			BlockTrafficRateLimitExceeded::SLUG,
		];
	}
}
