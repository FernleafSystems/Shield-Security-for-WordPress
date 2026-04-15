import "../css/plugin-onboarding.scss";
import { PluginOnboarding } from "./components/general/PluginOnboarding";

window.addEventListener( 'load', () => {
	const onboardingData = window.shield_vars_plugin_onboarding?.comps?.plugin_onboarding;
	if ( onboardingData ) {
		new PluginOnboarding( onboardingData );
	}
}, false );
