<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsSections;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Strings {

	use ModConsumer;

	/**
	 * @return array{name: string, summary: string, description: array}
	 * @throws \Exception
	 * @deprecated 19.1
	 */
	public function getOptionStrings( string $key ) :array {
		return ( new StringsOptions() )->getFor( $key );
	}

	/**
	 * @return array{title: string, short: string, summary: array}
	 * @throws \Exception
	 * @deprecated 19.1
	 */
	public function getSectionStrings( string $section ) :array {
		return ( new StringsSections() )->getFor( $section );
	}
}