<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'session_start'             => [
				'name'  => __( 'Session Started', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Session started for user ({{user_login}}) with session ID {{session_id}}.', 'wp-simple-firewall' ),
				],
			],
			'session_terminate'         => [
				'name'  => __( 'Session Terminated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Session terminated.', 'wp-simple-firewall' ),
				],
			],
			'session_terminate_current' => [
				'name'  => __( 'Current Session Terminated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Current session terminated for user ({{user_login}}) with session ID {{session_id}}.', 'wp-simple-firewall' ),
				],
			],
			'login_success'             => [
				'name'  => __( 'Login Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Login successful.', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_sessions' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Creates and Manages User Sessions.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_sessions' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}