document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus on verification code input
    const codeInput = document.getElementById('verification-code') || document.getElementById('totp-code');
    if (codeInput) {
        codeInput.focus();
    }
    
    // Format verification code as it's typed (optional)
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            // Remove non-digits
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 6 digits (standard TOTP length)
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
    }
});