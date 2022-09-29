<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Users;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Services\Services;

class ProfileSuspend extends BaseRender {

	const SLUG = 'render_profile_suspend';
	const TEMPLATE = '/admin/user/profile/suspend.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );
		$meta = $con->getUserMeta( Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] ) );
		return [
			'strings' => [
				'title'       => __( 'Suspend Account', 'wp-simple-firewall' ),
				'label'       => __( 'Check to un/suspend user account', 'wp-simple-firewall' ),
				'description' => __( 'The user can never login while their account is suspended.', 'wp-simple-firewall' ),
				'cant_manage' => __( 'Sorry, suspension for this account may only be managed by a security administrator.', 'wp-simple-firewall' ),
				'since'       => sprintf( '%s: %s', __( 'Suspended', 'wp-simple-firewall' ),
					Services::WpGeneral()->getTimeStringForDisplay( $meta->record->hard_suspended_at ) ),
			],
			'flags'   => [
				'can_manage_suspension' => !Services::WpUsers()->isUserAdmin( $user ) || $con->isPluginAdmin(),
				'is_suspended'          => $meta->record->hard_suspended_at > 0
			],
			'vars'    => [
				'form_field' => 'shield_suspend_user',
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id'
		];
	}
}