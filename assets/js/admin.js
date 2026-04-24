/**
 * ClawWP Admin Scripts
 *
 * Handles interactive elements on ClawWP admin pages:
 * pairing code submission, API key visibility toggle, etc.
 */
(function () {
    'use strict';

    var config = window.clawwpChat || {};

    // --- Pairing code submission ---
    var pairBtn = document.getElementById('clawwp_pair_submit');
    var pairInput = document.getElementById('clawwp_pair_code');
    var pairResult = document.getElementById('clawwp_pair_result');

    if (pairBtn && pairInput) {
        pairBtn.addEventListener('click', function () {
            var code = pairInput.value.trim();
            if (!code || code.length !== 6) {
                showPairResult('Please enter a valid 6-digit pairing code.', 'error');
                return;
            }

            pairBtn.disabled = true;
            pairBtn.textContent = 'Pairing...';

            fetch(config.restUrl + 'pair', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                },
                body: JSON.stringify({ code: code }),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Pairing failed');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    showPairResult('Successfully paired! Channel: ' + (data.channel || 'unknown'), 'success');
                    pairInput.value = '';
                    setTimeout(function () { location.reload(); }, 1500);
                })
                .catch(function (err) {
                    showPairResult(err.message, 'error');
                })
                .finally(function () {
                    pairBtn.disabled = false;
                    pairBtn.textContent = 'Pair';
                });
        });

        pairInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pairBtn.click();
            }
        });
    }

    function showPairResult(message, type) {
        if (!pairResult) return;
        pairResult.textContent = message;
        pairResult.className = 'clawwp-pair-result clawwp-pair-result--' + type;
    }

    // --- License activation ---
    var licenseActivateBtn = document.getElementById('clawwp_license_activate');
    var licenseKeyInput = document.getElementById('clawwp_license_key');
    var licenseResult = document.getElementById('clawwp_license_result');

    if (licenseActivateBtn && licenseKeyInput) {
        licenseActivateBtn.addEventListener('click', function () {
            var key = licenseKeyInput.value.trim();
            if (!key) {
                showLicenseResult('Please enter a license key.', 'error');
                return;
            }

            licenseActivateBtn.disabled = true;
            licenseActivateBtn.textContent = 'Activating...';

            fetch(config.restUrl + 'license/activate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                },
                body: JSON.stringify({ license_key: key }),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        data._status = response.status;
                        return data;
                    });
                })
                .then(function (data) {
                    if (data.success) {
                        showLicenseResult(data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showLicenseResult(data.message || 'Activation failed.', 'error');
                    }
                })
                .catch(function () {
                    showLicenseResult('Could not connect to the license server.', 'error');
                })
                .finally(function () {
                    licenseActivateBtn.disabled = false;
                    licenseActivateBtn.textContent = 'Activate';
                });
        });

        licenseKeyInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                licenseActivateBtn.click();
            }
        });
    }

    // --- License deactivation ---
    var licenseDeactivateBtn = document.getElementById('clawwp_license_deactivate');

    if (licenseDeactivateBtn) {
        licenseDeactivateBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to deactivate your Pro license?')) {
                return;
            }

            licenseDeactivateBtn.disabled = true;
            licenseDeactivateBtn.textContent = 'Deactivating...';

            fetch(config.restUrl + 'license/deactivate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                },
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        showLicenseResult(data.message || 'Deactivation failed.', 'error');
                    }
                })
                .catch(function () {
                    showLicenseResult('Could not connect to the server.', 'error');
                })
                .finally(function () {
                    licenseDeactivateBtn.disabled = false;
                    licenseDeactivateBtn.textContent = 'Deactivate License';
                });
        });
    }

    function showLicenseResult(message, type) {
        if (!licenseResult) return;
        licenseResult.textContent = message;
        licenseResult.className = 'clawwp-pair-result clawwp-pair-result--' + type;
    }

    // --- API key field: clear on focus if masked ---
    var apiKeyFields = document.querySelectorAll('input[type="password"][name*="api_key"], input[type="password"][name*="bot_token"]');
    apiKeyFields.forEach(function (field) {
        var originalValue = field.value;
        field.addEventListener('focus', function () {
            if (this.value.indexOf('\u2022') !== -1 || this.value.indexOf('*') !== -1) {
                this.value = '';
                this.type = 'text';
            }
        });
        field.addEventListener('blur', function () {
            if (this.value === '') {
                this.value = originalValue;
                this.type = 'password';
            }
        });
    });
})();
