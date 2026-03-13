<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class PostureAttention extends Base {

	protected function process() :array {
		$attention = self::con()->comps->site_query->attention();

		return [
			'generated_at' => $attention[ 'generated_at' ],
			'summary'      => $attention[ 'summary' ],
			'items'        => $attention[ 'items' ],
		];
	}
}
