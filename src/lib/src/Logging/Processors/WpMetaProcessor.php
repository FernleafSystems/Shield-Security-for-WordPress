<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

class WpMetaProcessor extends BaseMetaProcessor {

	/**
	 * @return array
	 */
	public function __invoke( array $record ) {
		$record[ 'extra' ][ 'meta_wp' ] = \array_filter( [
			'site_id' => \get_current_blog_id(),
		] );
		return $record;
	}
}