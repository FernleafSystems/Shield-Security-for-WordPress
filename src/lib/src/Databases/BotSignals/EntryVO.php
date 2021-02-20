<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

/**
 * Class EntryVO
 * @property string $ip
 * @property int    $notbot_at
 * @property int    $updated_at
 */
class EntryVO extends \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\EntryVO {

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {
		switch ( $key ) {

			case 'ip':
				$value = inet_ntop( parent::__get( $key ) );
				break;

			default:
				$value = parent::__get( $key );
				break;
		}
		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'ip':
				$value = inet_pton( $value );
				break;

			default:
				break;
		}

		parent::__set( $key, $value );
	}
}