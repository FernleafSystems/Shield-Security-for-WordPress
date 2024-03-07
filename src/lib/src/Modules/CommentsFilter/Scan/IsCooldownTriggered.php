<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IsCooldownTriggered {

	use PluginControllerConsumer;

	public function test() :bool {
		$CD = self::con()->opts->optGet( 'comments_cooldown' );
		return $CD > 0 && ( Services::Request()->ts() - self::con()->opts->optGet( 'last_comment_request_at' ) < $CD );
	}
}