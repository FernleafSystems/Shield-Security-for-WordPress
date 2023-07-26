<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	public function getEventStrings() :array {
		return [
			'suresend_fail'    => [
				'name'  => __( 'SureSend Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Failed to send email (type: {{slug}}) to "{{email}}" using SureSend.', 'wp-simple-firewall' ),
				],
			],
			'suresend_success' => [
				'name'  => __( 'SureSend Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Successfully sent email (type: {{slug}}) to "{{email}}" using SureSend.', 'wp-simple-firewall' ),
				],
			],
		];
	}

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
			'summary'     => ( isset( $aSummary ) && \is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	public function getOptionStrings( string $key ) :array {
		switch ( $key ) {

			case 'suresend_emails' :
				$name = __( 'SureSend Emails', 'wp-simple-firewall' );
				$summary = __( 'Select Which Shield Emails Should Be Sent Using SureSend', 'wp-simple-firewall' );
				$desc = [
					__( 'SureSend is a dedicated email delivery service from Shield Security.', 'wp-simple-firewall' ),
					__( 'The purpose is the improve WordPress email reliability for critical emails.', 'wp-simple-firewall' ),
					__( "If you're not using a dedicated email service provider to send WordPress emails, you should enable SureSend for these important emails.", 'wp-simple-firewall' ),
					__( "This isn't a replacement for a dedicated email service.", 'wp-simple-firewall' ),
					__( "Please read the information and blog links below to fully understand this service and its limitations.", 'wp-simple-firewall' ),
				];
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}
}