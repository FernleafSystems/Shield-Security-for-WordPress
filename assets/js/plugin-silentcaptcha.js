import { SilentCaptcha } from "./components/general/SilentCaptcha";

try {
	const baseData = window.shield_vars_silentcaptcha?.comps?.silentcaptcha;
	if ( baseData !== null && typeof baseData === 'object' ) {
		new SilentCaptcha( baseData );
	}
}
catch {
	void 0;
}
