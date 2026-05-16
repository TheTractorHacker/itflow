/* Discoverable passkey sign-in — no email required.
   The OS picker lets the user choose their account. */
(function () {
    'use strict';

    var btn = document.getElementById('passkeySignInBtn');
    if (!btn) return;

    btn.addEventListener('click', passkeySignIn);

    function b64uToBuf(str) {
        var b64 = str.replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) b64 += '=';
        return Uint8Array.from(atob(b64), function (c) { return c.charCodeAt(0); }).buffer;
    }

    function bufToB64u(buf) {
        var bytes = new Uint8Array(buf), s = '';
        bytes.forEach(function (b) { s += String.fromCharCode(b); });
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    async function passkeySignIn() {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Waiting for passkey…';

        try {
            // 1. Get challenge (no email sent)
            var beginResp = await fetch('passkey_auth_begin.php', { method: 'POST' });
            var options   = await beginResp.json();
            if (options.error) { showError(options.error); return; }

            options.challenge = b64uToBuf(options.challenge);
            // allowCredentials is empty — browser shows all available passkeys for this site

            // 2. OS picker → user selects their passkey
            var credential = await navigator.credentials.get({ publicKey: options });

            // 3. Send assertion to server
            var payload = {
                id:   credential.id,
                type: credential.type,
                response: {
                    clientDataJSON:    bufToB64u(credential.response.clientDataJSON),
                    authenticatorData: bufToB64u(credential.response.authenticatorData),
                    signature:         bufToB64u(credential.response.signature),
                    userHandle:        credential.response.userHandle
                                           ? bufToB64u(credential.response.userHandle)
                                           : null,
                },
            };

            var completeResp = await fetch('passkey_auth_complete.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            var result = await completeResp.json();

            if (result.ok) {
                window.location.href = result.redirect || '/agent/dashboard.php';
            } else {
                showError(result.error || 'Sign-in failed');
            }
        } catch (err) {
            if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
                showError(err.message);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-fingerprint mr-2"></i>Sign in with a Passkey';
        }
    }

    function showError(msg) {
        var el = document.getElementById('passkey-error');
        if (!el) {
            el = document.createElement('p');
            el.id = 'passkey-error';
            el.className = 'text-danger small text-center mt-2 mb-0';
            btn.parentNode.insertBefore(el, btn.nextSibling);
        }
        el.textContent = msg;
    }
})();
