<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Users;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * SecurityAdminNotRequired - This is so that admins that are not security admins may at least "view" current status.
 */
class ProfileSuspend extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_profile_suspend';
	public const TEMPLATE = '/admin/user/profile/suspend.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$WPU = Services::WpUsers();
		$currentUser = $WPU->getCurrentWpUser();
		$requestedUserID = (int)( $this->action_data[ 'user_id' ] ?? 0 );
		if ( $requestedUserID > 0 && $currentUser->ID !== $requestedUserID && !$WPU->isUserAdmin( $currentUser ) ) {
			throw new ActionException( __( 'Invalid profile request.', 'wp-simple-firewall' ) );
		}

		$editUser = $requestedUserID > 0 ? $WPU->getUserById( $requestedUserID ) : $currentUser;
		$meta = $con->user_metas->for( $editUser );
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
				'can_suspend'  => $con->comps->user_suspend->canManuallySuspend()
								  || ( !$WPU->isUserAdmin( $editUser ) && $WPU->isUserAdmin() ),
				'is_suspended' => $meta->record->hard_suspended_at > 0
			],
			'vars'    => [
				'form_field' => 'shield_suspend_user',
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [ 'user_id' ];
	}
}