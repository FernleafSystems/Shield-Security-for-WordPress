# Passkey Fixture Notes

`fixture_ceremony.json` is the replay fixture for the passkey integration suite.

The checked-in file is a deterministic cryptographic fixture that has already been validated against the current `web-auth/webauthn-lib` adapter. When refreshing it for migration work, prefer replacing it with a browser-captured ceremony from Shield's real passkey flow.

## Preferred refresh flow

1. Use a disposable local WordPress site with passkeys enabled.
2. On the profile page, trigger the existing registration flow in [`assets/js/components/userprofile/ProviderPasskeys.js`](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/assets/js/components/userprofile/ProviderPasskeys.js).
3. Capture the registration start AJAX payload and the `startRegistration()` browser result that is posted to `MfaPasskeyRegistrationVerify`.
4. Save the created MFA row plus the matching `user_meta.passkeys.user_key` value as `legacy_record` and `meta.user_handle_raw`.
5. On the login page, trigger the existing authentication flow in [`assets/js/components/login2fa/Login2faPasskey.js`](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/assets/js/components/login2fa/Login2faPasskey.js).
6. Capture the authentication start AJAX payload and base64-decode the submitted `icwp_wpsf_passkey_otp` field to recover the browser assertion JSON.
7. Update `meta`, `credential`, `registration`, `authentication`, and `legacy_record` in `fixture_ceremony.json`.
8. Run the targeted passkey integration tests and confirm registration verify, authentication verify, and login-intent replay still pass.
