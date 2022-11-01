<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class LoginIntentFormWpReplica extends BaseForm {

	const SLUG = 'render_shield_wploginreplica_form';
	const TEMPLATE = '/components/wplogin_replica/form.twig';

	protected function getRenderData() :array {

		return [
			'strings' => [
				'button_submit' => __( 'Complete Login', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'message_type' => 'info',
			],
		];
	}
}