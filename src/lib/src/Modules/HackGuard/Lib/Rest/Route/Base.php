<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

abstract class Base extends RouteBase {

	protected function getRouteArgsDefaults() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$possible = $opts->getScanSlugs();
		return [
			'scan_slugs' => [
				'description' => 'Comma-separated scan slugs include.',
				'type'        => 'string',
				'required'    => false,
				'pattern'     => sprintf( '^(((%s),?)+)?$', implode( '|', $possible ) ),
			],
		];
	}
}