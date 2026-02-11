<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;

class BotSignalsRecord {

	private string $ipAddress = '';

	private const TIMESTAMP_FIELDS = [
		'created_at',
		'updated_at',
		'deleted_at',
		'notbot_at',
		'altcha_at',
		'frontpage_at',
		'loginpage_at',
		'bt404_at',
		'btcheese_at',
		'btfake_at',
		'btinvalidscript_at',
		'btauthorfishing_at',
		'btloginfail_at',
		'btlogininvalid_at',
		'btua_at',
		'btxml_at',
		'cooldown_at',
		'auth_at',
		'offense_at',
		'blocked_at',
		'unblocked_at',
		'bypass_at',
		'humanspam_at',
		'markspam_at',
		'unmarkspam_at',
		'captchapass_at',
		'captchafail_at',
		'ratelimit_at',
		'snsent_at',
	];

	public function getIP() :string {
		return $this->ipAddress;
	}

	/**
	 * @param string $IP
	 * @return $this
	 */
	public function setIP( $IP ) {
		$this->ipAddress = (string)$IP;
		return $this;
	}

	public function delete() :bool {
		return true;
	}

	public function retrieveNotBotAt() :int {
		return 0;
	}

	public function retrieve() :BotSignalRecord {
		return $this->buildRecord();
	}

	public function store( BotSignalRecord $record ) :bool {
		return true;
	}

	public function updateSignalFields( array $fields, ?int $ts = null ) :BotSignalRecord {
		$record = $this->buildRecord();

		foreach ( $fields as $field ) {
			if ( !empty( $field ) && \is_string( $field ) ) {
				$record->{$field} = $ts ?? 0;
			}
		}

		$record->modified = false;
		return $record;
	}

	public function updateSignalField( string $field, ?int $ts = null ) :BotSignalRecord {
		return $this->updateSignalFields( [ $field ], $ts );
	}

	private function buildRecord() :BotSignalRecord {
		$record = new BotSignalRecord();
		$record->ip = (string)$this->getIP();
		$record->ip_ref = 0;

		foreach ( self::TIMESTAMP_FIELDS as $field ) {
			$record->{$field} = 0;
		}

		$record->modified = false;
		return $record;
	}
}
