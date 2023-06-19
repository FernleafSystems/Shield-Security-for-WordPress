<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportPages;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapPages;

class Pages extends BasePosts {

	protected function isAllowablePostType( \WP_Post $post ) :bool {
		return $post->post_type === 'page';
	}

	public function getReporter() :ZoneReportPages {
		return new ZoneReportPages();
	}

	public function getSnapper() :SnapPages {
		return new SnapPages();
	}
}