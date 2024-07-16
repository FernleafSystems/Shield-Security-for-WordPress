<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

abstract class ScansBase extends ScanBase {

	public function getRoutePath() :string {
		return '/scans';
	}
}