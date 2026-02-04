<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Email;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string[] $to
 * @property string[] $bcc
 * @property string[] $cc
 * @property string   $subject
 * @property string   $html
 * @property string   $text
 * @property bool     $is_alert
 * @property bool     $include_site_name
 */
class EmailVO extends DynPropertiesClass {

	public static function Factory( string $to, string $subject, string $html = '', string $text = '' ) :EmailVO {
		$email = new static();
		$email->to = $to;
		$email->subject = $subject;
		$email->html = $html;
		$email->text = $text;
		return $email;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'to':
			case 'subject':
			case 'html':
			case 'text':
				if ( !\is_string( $value ) ) {
					$value = '';
				}
				break;
			case 'is_alert':
				$value = (bool)$value;
				break;
			case 'cc':
			case 'bcc':
				$value = \array_map( '\trim', \is_array( $value ) ? $value : [] );
				break;
			default:
				break;
		}

		return $value;
	}

	public function buildSubject() :string {
		$WP = Services::WpGeneral();
		return \implode( ' ', \array_filter( [
			$this->is_alert ? '⚠️' : null,
			$this->include_site_name !== false ? \html_entity_decode( $WP->getSiteName(), \ENT_QUOTES ) : null,
			$this->subject,
		] ) );
	}
}