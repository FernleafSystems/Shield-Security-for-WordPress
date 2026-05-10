import { SilentCaptcha } from "./components/general/SilentCaptcha";

( 'shield_vars_silentcaptcha' in window ) && new SilentCaptcha( window.shield_vars_silentcaptcha.comps.silentcaptcha );
