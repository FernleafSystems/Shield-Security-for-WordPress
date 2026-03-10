<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

class PasskeyAdapterContext {

	public string $relyingPartyId;

	public string $relyingPartyName;

	public string $userName;

	public string $userHandle;

	public string $userDisplayName;

	public string $userAvatarUrl;

	public function __construct(
		string $relyingPartyId,
		string $relyingPartyName,
		string $userName,
		string $userHandle,
		string $userDisplayName,
		string $userAvatarUrl
	) {
		$this->relyingPartyId = $relyingPartyId;
		$this->relyingPartyName = $relyingPartyName;
		$this->userName = $userName;
		$this->userHandle = $userHandle;
		$this->userDisplayName = $userDisplayName;
		$this->userAvatarUrl = $userAvatarUrl;
	}
}
