<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

readonly class PasskeyAdapterContext {

	public function __construct(
		public string $relyingPartyId,
		public string $relyingPartyName,
		public string $userName,
		public string $userHandle,
		public string $userDisplayName,
		public string $userAvatarUrl,
		public ?string $relyingPartyOrigin = null
	) {
	}
}
