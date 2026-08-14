<?php

/**
 * Messages d'authentification.
 *
 * `failed` est volontairement vague sur la cause (ni « cet e-mail est inconnu », ni
 * « mot de passe incorrect ») : distinguer les deux permettrait à un tiers de savoir
 * quelles adresses ont un compte dans l'office.
 */
return [

    'failed'   => 'Ces identifiants ne correspondent à aucun compte.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Merci de réessayer dans :seconds secondes.',

];
