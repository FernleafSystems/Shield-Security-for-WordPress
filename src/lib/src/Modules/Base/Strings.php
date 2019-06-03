<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Strings {

	use ModConsumer;

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [];
	}

	/**
	 * @param string $sKey
	 * @return string[]
	 */
	public function getAuditMessage( $sKey ) {
		$aMsg = $this->getAuditMessages();
		return isset( $aMsg[ $sKey ] ) ? $aMsg[ $sKey ] : [];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sOptKey ) );
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

			case 'section_user_messages' :
				$sTitle = __( 'User Messages', 'wp-simple-firewall' );
				$sTitleShort = __( 'Messages', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Customize the messages displayed to the user.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use this section if you need to communicate to the user in a particular manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Hint', 'wp-simple-firewall' ), sprintf( __( 'To reset any message to its default, enter the text exactly: %s', 'wp-simple-firewall' ), 'default' ) )
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}
}