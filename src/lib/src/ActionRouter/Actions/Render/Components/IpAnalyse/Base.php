<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Services\Services;

class Base extends Render\BaseRender {

	protected function getRequiredDataKeys() :array {
		return [
			'ip'
		];
	}

	protected function getTimeAgo( int $ts ) :string {
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $ts )
					   ->diffForHumans();
	}
}