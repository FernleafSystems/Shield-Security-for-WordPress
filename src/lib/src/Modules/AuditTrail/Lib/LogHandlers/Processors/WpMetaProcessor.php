<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Processors;

use Monolog\Processor\ProcessorInterface;

class WpMetaProcessor implements ProcessorInterface {

	/**
	 * @param array $record
	 * @return array
	 */
	public function __invoke( array $record ) {
		$record[ 'extra' ][ 'meta_wp' ] = array_filter( [
			'site_id' => \get_current_blog_id(),
		] );
		return $record;
	}
}
