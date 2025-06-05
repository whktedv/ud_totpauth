Anleitung zur Installation und Verwendung

1. Extension installieren

2. Füge die Extension zu deinem Template hinzu

3. Seiten erstellen:
- Erstelle eine Seite für die TOTP-Einrichtung und füge das Plugin mit der Aktion "Show QR code setup" auf der Einrichtungsseite hinzu
- Erstelle eine Seite für die TOTP-Verifizierung und füge das Plugin mit der Aktion "Verify TOTP code" auf der Verifizierungsseite hinzu
- Erstelle eine Seite für die "E-Mail warten"-Ansicht und füge das Plugin mit der Aktion 'emailVerificationRequired'
- Erstelle eine Seite für die "E-Mail verifizieren"-Ansicht und füge das Plugin mit der Aktion 'verifyEmail'

4. Im Typoscript in den Constants die beiden Werte eintragen:
plugin.tx_udtotpauth_totpsetup.settings.verifyPageId = <id der Verifizierungs-Seite>
plugin.tx_udtotpauth_totpsetup.settings.loginPageId = <id der Login-Seite>
plugin.tx_udtotpauth_totpsetup.settings.emailWaitPageId = <id der E-Mail-Warteseite>
plugin.tx_udtotpauth_totpsetup.settings.emailVerifyPageId = <id der E-Mail-Verifizierungsseite>
plugin.tx_udtotpauth_totpsetup.settings.applicationName = <Name der Anwendung>
plugin.tx_udtotpauth_totpsetup.settings.enabled = 1

5. Erstelle im geschützten Bereich einen Link auf die Einrichtungsseite.

*** Wie die Extension funktioniert ***

Standardmäßig ist die 2FA per E-Mail aktiviert. Bei jedem Login erhält der Benutzer eine E-Mail mit einem Link.
Nach Klick auf den Link in der E-Mail wird der Benutzer automatisch eingeloggt und zu der Seite umgeleitet, die nach dem Login erscheint.

Ablauf der Benutzerregistrierung für TOTP:
- Benutzer meldet sich an und navigiert zur 2FA-Einrichtungsseite
- System generiert einen einzigartigen geheimen Schlüssel und zeigt QR-Code an
- Benutzer scannt den QR-Code mit einer Authenticator-App
- Benutzer gibt den Code aus der App ein, um die Einrichtung zu bestätigen
- System speichert den geheimen Schlüssel und aktiviert 2FA für den Benutzer

Ablauf der Anmeldung mit TOTP:
- Benutzer gibt Benutzername und Passwort ein (Standard-TYPO3-Login)
- Nach erfolgreicher Passwort-Authentifizierung prüft der PSR14-Event-Listener der Extension, ob 2FA aktiv ist
- Wenn 2FA aktiv ist, wird der Benutzer zur Verifizierungsseite weitergeleitet
- Benutzer gibt den Code aus der Authenticator-App ein
- Bei korrektem Code wird der Benutzer zur ursprünglich angeforderten Seite weitergeleitet

Sicherheitsmerkmale:
- Alle TOTP-Geheimnisse werden in der Datenbank gespeichert
- TOTP-Codes sind zeitbasiert und nur für 30 Sekunden gültig
- Bei fehlgeschlagenen Verifizierungsversuchen bleibt der Benutzer auf der Verifizierungsseite
- Optional kann eine einmal eingerichtete 2FA per TOTP auch wieder auf 2FA per E-Mail umgestellt werden
