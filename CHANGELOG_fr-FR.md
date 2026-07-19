# 0.9.0-beta.1

Version préliminaire pour le développement interne, l'assurance qualité et les
tests sandbox/staging. Pas encore soumise au Shopware Store. L'API publique et
les espaces de noms peuvent encore changer avant la version `1.0.0`.

- Paiement Flutterwave pour Shopware 6 : carte, virement bancaire et mobile money.
- La vérification du paiement contrôle le statut, le montant et la devise avant qu'une commande ne soit marquée comme payée.
- Remboursements depuis la page de détail de la commande, y compris les remboursements partiels, avec historique des remboursements en temps réel et protection côté serveur contre le sur-remboursement.
- Autorisation d'administration dédiée "Remboursement Flutterwave" pouvant être attribuée à des rôles (dépend de l'autorisation d'édition des commandes).
- Gestion des webhooks pour `charge.completed` et `refund.completed`, avec vérification de signature et traitement idempotent, résistant aux doublons.
- Vérification du compte bancaire dans le compte client (résolution de compte via Flutterwave), avec un champ BVN optionnel.
- Les montants sont transmis à Flutterwave dans l'unité principale de la devise, comme son API l'exige, et comparés avec précision selon les décimales propres à chaque devise — y compris les devises à zéro et trois décimales (par ex. RWF, UGX, KWD). Le plugin ne crée pas de devises ni de langues dans la boutique.
- Interface du plugin traduite en anglais, allemand et français.
- Journalisation configurable (par canal de vente), mode sandbox/live et montant minimum de remboursement.
- Supporte Shopware 6.6 et 6.7.
