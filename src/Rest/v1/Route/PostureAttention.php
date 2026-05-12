<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class PostureAttention extends PostureBase {

	public function getRoutePath() :string {
		return '/attention';
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\PostureAttention::class;
	}
}
