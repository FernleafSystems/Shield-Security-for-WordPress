<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages\RenderBlockAuthorFishing;

class BlockAuthorFishing extends Base {

	const SLUG = 'block_author_fishing';

	protected function execResponse() :bool {
		( new RenderBlockAuthorFishing() )
			->setMod( $this->getCon()->getModule_Lockdown() )
			->setAuxData( $this->getConsolidatedConditionMeta() )
			->display();
		return true;
	}
}