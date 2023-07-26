<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

class CustomPostTypes extends BasePosts {

	protected function isAllowablePostType( \WP_Post $post ) :bool {
		return !\in_array( $post->post_type, [ 'post', 'page', 'attachment' ] );
	}

//	public function getReporter() :ZoneReportPosts {
//		return new ZoneReportPosts();
//	}
//
//	public function getSnapper() :SnapPosts {
//		return new SnapPosts();
//	}
}