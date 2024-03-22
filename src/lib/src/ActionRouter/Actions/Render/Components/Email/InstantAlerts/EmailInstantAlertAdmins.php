<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;

class EmailInstantAlertAdmins extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_admins';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					__( 'A change in the constituency of administrators has been detected.', 'wp-simple-firewall' ),
				],
				'outro' => [
					__( 'These changes may be normal and expected, but if anything looks strange, you should review your list of site admins immediately.', 'wp-simple-firewall' )
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertGroups = [];
		foreach ( \array_filter( $this->action_data[ 'alert_data' ] ) as $alertKey => $alertItems ) {
			$alertGroups[ $alertKey ] = [
				'title' => sprintf( '%s: %s', __( 'Change Detected', 'wp-simple-firewall' ), $this->titleFor( $alertKey ) ),
				'items' => \array_map(
					function ( string $item ) {
						return [
							'text' => sprintf( '%s: %s', __( 'Username' ), $item ),
							'href' => '',
						];
					},
					$alertItems
				)
			];
		}
		return $alertGroups;
	}

	private function titleFor( string $key ) :string {
		return [
				   'added'      => 'New Admin(s)',
				   'removed'    => 'Deleted Admin(s)',
				   'user_pass'  => 'Password Updated',
				   'user_email' => 'Email Updated',
				   'promoted'   => 'Promoted To Admin',
				   'demoted'    => 'Demoted From Admin',
			   ][ $key ];
	}
}