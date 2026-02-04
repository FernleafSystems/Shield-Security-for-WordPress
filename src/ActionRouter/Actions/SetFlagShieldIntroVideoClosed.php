<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class SetFlagShieldIntroVideoClosed extends BaseAction {

	use Traits\SecurityAdminRequired;

	public const SLUG = 'set_flag_shield_intro_video_closed';

	protected function exec() {
		self::con()->opts->optSet( 'v20_intro_closed_at', Services::Request()->ts() );
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}