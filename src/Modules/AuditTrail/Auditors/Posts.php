<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportPosts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapPosts;

class Posts extends BasePosts {

	protected function isAllowablePostType( \WP_Post $post ) :bool {
		return $post->post_type === 'post';
	}

	public function getReporter() :ZoneReportPosts {
		return new ZoneReportPosts();
	}

	public function getSnapper() :SnapPosts {
		return new SnapPosts();
	}
}