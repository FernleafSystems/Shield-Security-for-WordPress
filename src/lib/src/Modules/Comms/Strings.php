<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {
		switch ( $section ) {
			case 'section_suresend' :
				$title = __( 'SureSend Email', 'wp-simple-firewall' );
				$titleShort = __( 'SureSend Email', 'wp-simple-firewall' );
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		switch ( $key ) {

			case 'suresend_emails' :
				$sName = __( 'SureSend Emails', 'wp-simple-firewall' );
				$sSummary = __( 'Select Which Shield Emails Should Be Sent Using SureSend', 'wp-simple-firewall' );
				$sDescription = [
					__( 'SureSend is a dedicated email delivery service from Shield Security.', 'wp-simple-firewall' ),
					__( 'The purpose is the improve WordPress email reliability for critical emails.', 'wp-simple-firewall' ),
					__( "If you're relying on WordPress to send and deliver important emails, you should enable SureSend for these important emails.", 'wp-simple-firewall' ),
					__( "This isn't a replacement for a dedicated email service and if you're using a 3rd party email service, you probably won't need SureSend.", 'wp-simple-firewall' ),
				];
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

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() :array {
		return [
			'suresend_success' => [
				__( 'Attempt to send email using SureSend: %s', 'wp-simple-firewall' ),
				__( 'SureSend email success.', 'wp-simple-firewall' ),
			],
			'suresend_fail'    => [
				__( 'Attempt to send email using SureSend: %s', 'wp-simple-firewall' ),
				__( 'SureSend email failed.', 'wp-simple-firewall' ),
			],
		];
	}
}