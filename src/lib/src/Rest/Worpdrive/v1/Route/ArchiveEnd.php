<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class ArchiveEnd extends ArchiveBegin {

	public function getRoutePath() :string {
		return '/archive_end';
	}
}