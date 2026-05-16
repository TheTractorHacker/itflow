/* Passkey sign-in for login.php — CSP-compliant (no inline scripts/styles) */
(function () {
    'use strict';

    var emailInput = document.querySelector('[name="email"]');
    var wrapper    = document.getElementById('passkey-btn-wrapper');
    var btn        = document.getElementById('passkeySignInBtn');

    if (!emailInput || !wrapper || !btn) return;

    // Show button only when an email is typed
    function toggleBtn() {
        if (emailInput.value.trim()) {
            wrapper.classList.remove('d-none');
        } else {
            wrapper.classList.add('d-none');
        }
    }
    emailInput.addEventListener('input', toggleBtn);
    toggleBtn(); // run once on load

    btn.addEventListener('click', passkeySignIn);

    // ── base64url helpers ─────────────────────────────────────────────────────
    function b64uToBuf(str) {
        var b64 = str.replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) b64 += '=';
        return Uint8Array.from(atob(b64), function (c) { return c.charCodeAt(0); }).buffer;
    }

    function bufToB64u(buf) {
        var bytes = new Uint8Array(buf);
        var s = '';
        bytes.forEach(function (b) { s += String.fromCharCode(b); });
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    // ── main sign-in flow ─────────────────────────────────────────────────────
    async function passkeySignIn() {
        var email = emailInput.value.trim();
        if (!email) { alert('Please enter your email address first.'); return; }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Waiting…';

        try {
            var beginResp = await fetch('passkey_auth_begin.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email: email }),
            });
            var options = await beginResp.json();
            if (options.error) { alert(options.error); return; }

            options.challenge = b64uToBuf(options.challenge);
            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map(function (c) {
                    return Object.assign({}, c, { id: b64uToBuf(c.id) });
                });
            }

            var credential = await navigator.credentials.get({ publicKey: options });

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
                alert('Passkey sign-in failed: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
                alert('Error: ' + err.message);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-fingerprint mr-2"></i>Use a Passkey';
        }
    }
})();
