<?php

class TerminologyService {
    public static function forFormType($formType) {
        $type = (string) ($formType ?? 'Event');

        switch ($type) {
            case 'Shop':
            case 'Checkout':
            case 'PaymentForm':
            case 'Product':
                return [
                    'recipientListTitle' => 'Liste des contacts',
                    'contactEmailPlaceholder' => 'Email du contact',
                    'publicLinkDescription' => "Ce lien ouvre un formulaire qui demande l'email de commande, puis bascule la personne sur son questionnaire avec token individuel.",
                    'lookupTitle' => 'Identifier votre commande',
                    'lookupDescription' => "Saisissez l'adresse email utilisée lors de votre commande pour ouvrir votre questionnaire personnel.",
                    'lookupEmailLabel' => 'Email de commande',
                    'lookupEmailError' => "Veuillez saisir l'email utilisé lors de votre commande.",
                    'lookupNotice' => "Si cette adresse correspond à une commande pour cette campagne, vous allez être redirigé vers votre questionnaire personnel.",
                    'fallbackThankYouLine' => 'Merci pour votre récente commande liée à',
                ];

            case 'Membership':
                return [
                    'recipientListTitle' => 'Liste des contacts',
                    'contactEmailPlaceholder' => 'Email du contact',
                    'publicLinkDescription' => "Ce lien ouvre un formulaire qui demande l'email utilisé lors de l'adhésion, puis bascule la personne sur son questionnaire avec token individuel.",
                    'lookupTitle' => 'Retrouver votre adhésion',
                    'lookupDescription' => "Saisissez l'adresse email utilisée lors de votre adhésion pour ouvrir votre questionnaire personnel.",
                    'lookupEmailLabel' => "Email d'adhésion",
                    'lookupEmailError' => "Veuillez saisir l'email utilisé lors de votre adhésion.",
                    'lookupNotice' => "Si cette adresse correspond à une adhésion pour cette campagne, vous allez être redirigé vers votre questionnaire personnel.",
                    'fallbackThankYouLine' => 'Merci pour votre récente adhésion à',
                ];

            case 'Donation':
            case 'Crowdfunding':
                return [
                    'recipientListTitle' => 'Liste des contacts',
                    'contactEmailPlaceholder' => 'Email du contact',
                    'publicLinkDescription' => "Ce lien ouvre un formulaire qui demande l'email utilisé pour le don, puis bascule la personne sur son questionnaire avec token individuel.",
                    'lookupTitle' => 'Retrouver votre questionnaire',
                    'lookupDescription' => "Saisissez l'adresse email utilisée pour votre don afin d'ouvrir votre questionnaire personnel.",
                    'lookupEmailLabel' => 'Email utilisé pour le don',
                    'lookupEmailError' => "Veuillez saisir l'email utilisé pour votre don.",
                    'lookupNotice' => "Si cette adresse correspond à un don pour cette campagne, vous allez être redirigé vers votre questionnaire personnel.",
                    'fallbackThankYouLine' => 'Merci pour votre récent soutien à',
                ];

            case 'Event':
            default:
                return [
                    'recipientListTitle' => 'Liste des contacts',
                    'contactEmailPlaceholder' => 'Email du contact',
                    'publicLinkDescription' => "Ce lien ouvre un formulaire qui demande l'email de réservation, puis bascule la personne sur son questionnaire avec token individuel.",
                    'lookupTitle' => 'Identifier votre réservation',
                    'lookupDescription' => "Saisissez l'adresse email utilisée lors de votre réservation pour ouvrir votre questionnaire personnel.",
                    'lookupEmailLabel' => 'Email de réservation',
                    'lookupEmailError' => "Veuillez saisir l'email utilisé lors de votre réservation.",
                    'lookupNotice' => "Si cette adresse correspond à une réservation pour cette campagne, vous allez être redirigé vers votre questionnaire personnel.",
                    'fallbackThankYouLine' => 'Merci pour votre récente participation à',
                ];
        }
    }
}
