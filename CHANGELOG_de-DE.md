# 0.9.0-beta.1

Vorabversion für interne Entwicklung, QA und Sandbox-/Staging-Tests. Noch
nicht im Shopware Store eingereicht. Die öffentliche API und Namespaces können
sich vor `1.0.0` noch ändern.

- Flutterwave-Zahlung für Shopware 6: Karte, Banküberweisung und Mobile Money.
- Zahlungsprüfung kontrolliert Status, Betrag und Währung, bevor eine Bestellung als bezahlt markiert wird.
- Rückerstattungen aus der Bestelldetailseite, inklusive Teilrückerstattungen, mit Live-Rückerstattungsverlauf und serverseitiger Absicherung gegen Überrückerstattung.
- Eigene Berechtigung „Flutterwave-Rückerstattung", die Rollen zugewiesen werden kann (abhängig von der Bestell-Editor-Berechtigung).
- Webhook-Verarbeitung für `charge.completed` und `refund.completed` mit Signaturprüfung sowie idempotenter, wiederholungssicherer Verarbeitung.
- Bankkontoprüfung im Kundenkonto (Kontoauflösung über Flutterwave), mit optionalem BVN-Feld.
- Beträge werden im Hauptwährungsformat an Flutterwave übermittelt, wie von dessen API erwartet, und mit der jeweiligen Dezimalgenauigkeit exakt verglichen — auch bei Währungen mit null und drei Dezimalstellen (z. B. RWF, UGX, KWD). Das Plugin legt keine Währungen oder Sprachen im Shop an.
- Plugin-Oberfläche auf Englisch, Deutsch und Französisch verfügbar.
- Konfigurierbares Logging (pro Verkaufskanal), Sandbox-/Live-Modus und ein Mindestrückerstattungsbetrag.
- Unterstützt Shopware 6.6 und 6.7.
