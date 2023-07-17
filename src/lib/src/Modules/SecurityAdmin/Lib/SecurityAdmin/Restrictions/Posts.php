<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

class Posts extends BaseCapabilitiesRestrict {

	public const AREA_SLUG = 'posts';

	protected function isCapabilityToBeRestricted( string $cap ) :bool {
		return \in_array( $cap, $this->getApplicableCapabilities() )
			   && \in_array(
				   \str_replace( [
					   '_posts',
					   '_pages',
					   '_post',
					   '_page'
				   ], '', $cap ), //Order of items in this array is important!
				   $this->getRestrictedCapabilities()
			   );
	}

	protected function getApplicableCapabilities() :array {
		return [
			'edit_post',
			'publish_post',
			'delete_post',
			'edit_posts',
			'publish_posts',
			'delete_posts',
			'edit_page',
			'publish_page',
			'delete_page',
			'edit_pages',
			'publish_pages',
			'delete_pages'
		];
	}
}