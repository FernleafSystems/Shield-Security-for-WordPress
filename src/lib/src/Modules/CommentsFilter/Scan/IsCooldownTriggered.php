<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IsCooldownTriggered {

	use ModConsumer;

	public function test() :bool {
		$CD = $this->opts()->getOpt( 'comments_cooldown' );
		return $CD > 0 && ( Services::Request()->ts() - $this->opts()->getOpt( 'last_comment_request_at' ) < $CD );
	}
}