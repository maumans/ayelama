<?php

/**
 * Messages de validation en français.
 *
 * L'application n'avait aucun dossier `lang/` et tournait en locale `en` : toutes les
 * erreurs de validation s'affichaient en anglais (« The email field is required. »), y
 * compris sur l'écran de connexion.
 *
 * Le tableau `attributes` en fin de fichier est ce qui fait la différence entre
 * « Le champ email est obligatoire » et « Le champ adresse e-mail est obligatoire » :
 * il donne à chaque nom de champ technique son libellé métier. Il recense les champs
 * réellement validés par l'application — tout champ ajouté à un FormRequest devrait y
 * être déclaré.
 */
return [

    'accepted'             => 'Le champ :attribute doit être accepté.',
    'accepted_if'          => 'Le champ :attribute doit être accepté quand :other vaut :value.',
    'active_url'           => "Le champ :attribute n'est pas une URL valide.",
    'after'                => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal'       => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'alpha'                => 'Le champ :attribute doit contenir uniquement des lettres.',
    'alpha_dash'           => 'Le champ :attribute doit contenir uniquement des lettres, des chiffres, des tirets et des underscores.',
    'alpha_num'            => 'Le champ :attribute doit contenir uniquement des chiffres et des lettres.',
    'array'                => 'Le champ :attribute doit être un tableau.',
    'ascii'                => 'Le champ :attribute ne peut contenir que des caractères alphanumériques et des symboles sur un octet.',
    'before'               => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal'      => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'between'              => [
        'array'   => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file'    => 'Le fichier :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string'  => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean'              => 'Le champ :attribute doit être vrai ou faux.',
    'can'                  => 'Le champ :attribute contient une valeur non autorisée.',
    'confirmed'            => 'Le champ de confirmation :attribute ne correspond pas.',
    'contains'             => 'Le champ :attribute ne contient pas une valeur requise.',
    'current_password'     => 'Le mot de passe est incorrect.',
    'date'                 => "Le champ :attribute n'est pas une date valide.",
    'date_equals'          => 'Le champ :attribute doit être une date égale à :date.',
    'date_format'          => 'Le champ :attribute ne correspond pas au format :format.',
    'decimal'              => 'Le champ :attribute doit avoir :decimal décimales.',
    'declined'             => 'Le champ :attribute doit être refusé.',
    'declined_if'          => 'Le champ :attribute doit être refusé quand :other vaut :value.',
    'different'            => 'Les champs :attribute et :other doivent être différents.',
    'digits'               => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between'       => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'dimensions'           => "Le champ :attribute n'a pas des dimensions d'image valides.",
    'distinct'             => 'Le champ :attribute a une valeur en double.',
    'doesnt_end_with'      => 'Le champ :attribute ne doit pas se terminer par une des valeurs suivantes : :values.',
    'doesnt_start_with'    => 'Le champ :attribute ne doit pas commencer par une des valeurs suivantes : :values.',
    'email'                => "Le champ :attribute doit être une adresse e-mail valide.",
    'ends_with'            => 'Le champ :attribute doit se terminer par une des valeurs suivantes : :values.',
    'enum'                 => 'La valeur sélectionnée pour :attribute est invalide.',
    'exists'              => 'La valeur sélectionnée pour :attribute est invalide.',
    'extensions'           => 'Le champ :attribute doit avoir une des extensions suivantes : :values.',
    'file'                 => 'Le champ :attribute doit être un fichier.',
    'filled'               => 'Le champ :attribute doit avoir une valeur.',
    'gt'                   => [
        'array'   => 'Le champ :attribute doit contenir plus de :value éléments.',
        'file'    => 'Le fichier :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string'  => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'gte'                  => [
        'array'   => 'Le champ :attribute doit contenir au moins :value éléments.',
        'file'    => 'Le fichier :attribute doit peser au moins :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string'  => 'Le champ :attribute doit contenir au moins :value caractères.',
    ],
    'hex_color'            => 'Le champ :attribute doit être une couleur hexadécimale valide.',
    'image'                => 'Le champ :attribute doit être une image.',
    'in'                   => 'La valeur sélectionnée pour :attribute est invalide.',
    'in_array'             => "Le champ :attribute n'existe pas dans :other.",
    'integer'              => 'Le champ :attribute doit être un nombre entier.',
    'ip'                   => 'Le champ :attribute doit être une adresse IP valide.',
    'ipv4'                 => 'Le champ :attribute doit être une adresse IPv4 valide.',
    'ipv6'                 => 'Le champ :attribute doit être une adresse IPv6 valide.',
    'json'                 => 'Le champ :attribute doit être un document JSON valide.',
    'list'                 => 'Le champ :attribute doit être une liste.',
    'lowercase'            => 'Le champ :attribute doit être en minuscules.',
    'lt'                   => [
        'array'   => 'Le champ :attribute doit contenir moins de :value éléments.',
        'file'    => 'Le fichier :attribute doit peser moins de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'string'  => 'Le champ :attribute doit contenir moins de :value caractères.',
    ],
    'lte'                  => [
        'array'   => 'Le champ :attribute doit contenir au plus :value éléments.',
        'file'    => 'Le fichier :attribute doit peser au plus :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
        'string'  => 'Le champ :attribute doit contenir au plus :value caractères.',
    ],
    'mac_address'          => 'Le champ :attribute doit être une adresse MAC valide.',
    'max'                  => [
        'array'   => 'Le champ :attribute ne peut contenir plus de :max éléments.',
        'file'    => 'Le fichier :attribute ne peut peser plus de :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut être supérieur à :max.',
        'string'  => 'Le champ :attribute ne peut contenir plus de :max caractères.',
    ],
    'max_digits'           => 'Le champ :attribute ne peut avoir plus de :max chiffres.',
    'mimes'                => 'Le champ :attribute doit être un fichier de type : :values.',
    'mimetypes'            => 'Le champ :attribute doit être un fichier de type : :values.',
    'min'                  => [
        'array'   => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file'    => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
        'string'  => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'min_digits'           => 'Le champ :attribute doit avoir au moins :min chiffres.',
    'missing'              => 'Le champ :attribute doit être absent.',
    'missing_if'           => 'Le champ :attribute doit être absent quand :other vaut :value.',
    'missing_unless'       => 'Le champ :attribute doit être absent sauf si :other vaut :value.',
    'missing_with'         => 'Le champ :attribute doit être absent quand :values est présent.',
    'missing_with_all'     => 'Le champ :attribute doit être absent quand :values sont présents.',
    'multiple_of'          => 'Le champ :attribute doit être un multiple de :value.',
    'not_in'               => 'La valeur sélectionnée pour :attribute est invalide.',
    'not_regex'            => 'Le format du champ :attribute est invalide.',
    'numeric'              => 'Le champ :attribute doit être un nombre.',
    'password'             => [
        'letters'       => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed'         => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers'       => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols'       => 'Le champ :attribute doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Le champ :attribute est apparu dans une fuite de données. Merci de choisir un autre mot de passe.',
    ],
    'present'              => 'Le champ :attribute doit être présent.',
    'present_if'           => 'Le champ :attribute doit être présent quand :other vaut :value.',
    'present_unless'       => 'Le champ :attribute doit être présent sauf si :other vaut :value.',
    'present_with'         => 'Le champ :attribute doit être présent quand :values est présent.',
    'present_with_all'     => 'Le champ :attribute doit être présent quand :values sont présents.',
    'prohibited'           => 'Le champ :attribute est interdit.',
    'prohibited_if'        => 'Le champ :attribute est interdit quand :other vaut :value.',
    'prohibited_unless'    => 'Le champ :attribute est interdit sauf si :other fait partie de :values.',
    'prohibits'            => 'Le champ :attribute interdit la présence de :other.',
    'regex'                => 'Le format du champ :attribute est invalide.',
    'required'             => 'Le champ :attribute est obligatoire.',
    'required_array_keys'  => 'Le champ :attribute doit contenir des entrées pour : :values.',
    'required_if'          => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_if_accepted' => 'Le champ :attribute est obligatoire quand :other est accepté.',
    'required_if_declined' => 'Le champ :attribute est obligatoire quand :other est refusé.',
    'required_unless'      => 'Le champ :attribute est obligatoire sauf si :other fait partie de :values.',
    'required_with'        => 'Le champ :attribute est obligatoire quand :values est présent.',
    'required_with_all'    => 'Le champ :attribute est obligatoire quand :values sont présents.',
    'required_without'     => 'Le champ :attribute est obligatoire quand :values est absent.',
    'required_without_all' => 'Le champ :attribute est obligatoire quand aucun de :values est présent.',
    'same'                 => 'Les champs :attribute et :other doivent être identiques.',
    'size'                 => [
        'array'   => 'Le champ :attribute doit contenir :size éléments.',
        'file'    => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit être égal à :size.',
        'string'  => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'starts_with'          => 'Le champ :attribute doit commencer par une des valeurs suivantes : :values.',
    'string'               => 'Le champ :attribute doit être une chaîne de caractères.',
    'timezone'             => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique'               => 'La valeur du champ :attribute est déjà utilisée.',
    'uploaded'             => "Le téléversement du fichier :attribute a échoué.",
    'uppercase'            => 'Le champ :attribute doit être en majuscules.',
    'url'                  => "Le champ :attribute doit être une URL valide.",
    'ulid'                 => 'Le champ :attribute doit être un ULID valide.',
    'uuid'                 => 'Le champ :attribute doit être un UUID valide.',

    /*
    |---------------------------------------------------------------------------
    | Messages personnalisés par champ
    |---------------------------------------------------------------------------
    */

    'custom' => [
        'objet' => [
            'min' => "L'objet du dossier doit faire au moins :min caractères — il doit décrire le dossier de façon reconnaissable.",
        ],
        'fichier' => [
            'mimes' => 'Le fichier doit être au format : :values.',
            'max'   => 'Le fichier ne peut pas dépasser :max Ko (20 Mo).',
        ],
        'current_password' => [
            'current_password' => 'Le mot de passe actuel est incorrect.',
        ],
        // `required_if:type,physique` produirait « obligatoire quand type vaut physique »,
        // exact mais peu naturel sur un formulaire de fiche client.
        'prenom_nom' => [
            'required_if' => 'Le nom et les prénoms sont obligatoires pour une personne physique.',
        ],
        'nom_famille' => [
            'required_if' => 'Le nom de famille est obligatoire pour une personne physique.',
        ],
        'denomination' => [
            'required_if' => 'La dénomination est obligatoire pour une personne morale.',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Libellés métier des champs
    |---------------------------------------------------------------------------
    |
    | Sans ce tableau, les messages citent le nom technique de la colonne
    | (« Le champ prenom_nom est obligatoire »). Recense les champs réellement
    | validés par l'application — à compléter en même temps qu'un FormRequest.
    */

    'attributes' => [
        // Authentification et compte
        'email'                  => 'adresse e-mail',
        'password'               => 'mot de passe',
        'password_confirmation'  => 'confirmation du mot de passe',
        'current_password'       => 'mot de passe actuel',
        'name'                   => 'nom complet',
        'initiales'              => 'initiales',
        'telephone'              => 'téléphone',
        'roles'                  => 'rôles',
        'role'                   => 'rôle',
        'actif'                  => 'compte actif',
        'remember_device'        => 'appareil de confiance',

        // Dossier
        'objet'                  => 'objet du dossier',
        'valeur'                 => 'valeur du dossier',
        'echeance'               => 'échéance',
        'urgent'                 => 'dossier urgent',
        'notes'                  => 'notes',
        'etape'                  => 'étape',
        'type_acte_id'           => "type d'acte",
        'type_acte_ids'          => "types d'actes",
        'notaire_id'             => 'notaire',
        'reviseur_id'            => 'certificateur',
        'formaliste_id'          => 'formaliste',
        'default_notaire_id'     => 'notaire par défaut',
        'default_reviseur_id'    => 'certificateur par défaut',
        'default_formaliste_id'  => 'formaliste par défaut',
        'date_signature_client'  => 'date de signature du client',
        'date_signature_notaire' => 'date de signature du notaire',
        'donnees'                => 'questionnaire',
        'brouillon_id'           => 'brouillon',
        'etat'                   => 'état du brouillon',

        // Personnes et fiches clients
        'parties'                => 'personnes du dossier',
        'nom'                    => 'nom',
        'prenom_nom'             => 'nom et prénoms',
        'nom_famille'            => 'nom de famille',
        'prenoms'                => 'prénoms',
        'civilite'               => 'civilité',
        'denomination'           => 'dénomination',
        'forme'                  => 'forme juridique',
        'rccm'                   => 'numéro RCCM',
        'representant_legal'     => 'représentant légal',
        'representant_qualite'   => 'qualité du représentant',
        'client_id'              => 'client',
        'client_role'            => 'qualité du client',
        'cni'                    => "pièce d'identité",
        'piece_type'             => "type de pièce d'identité",
        'piece_numero'           => 'numéro de la pièce',
        'piece_delivree_le'      => 'date de délivrance de la pièce',
        'piece_delivree_a'       => 'lieu de délivrance de la pièce',
        'piece_expire_le'        => "date d'expiration de la pièce",
        'ne_a'                   => 'lieu de naissance',
        'date_naissance'         => 'date de naissance',
        'nationalite'            => 'nationalité',
        'situation_matrimoniale' => 'situation matrimoniale',
        'regime_matrimonial'     => 'régime matrimonial',
        'adresse'                => 'adresse',
        'quartier'               => 'quartier',
        'commune'                => 'commune',
        'demeurant_ville'        => 'ville de résidence',
        'pays'                   => 'pays',
        'siege'                  => 'siège social',

        // Documents et pièces
        'fichier'                => 'fichier',
        'pieces'                 => 'pièces justificatives',
        'pieces_requises'        => 'pièces requises',
        'type_document'          => 'type de document',
        'categorie'              => 'catégorie',
        'version'                => 'version',
        'label'                  => 'libellé',
        'rubrique'               => 'rubrique',

        // Formalités
        'organisme'              => 'organisme',
        'libelle'                => 'libellé',
        'statut'                 => 'statut',
        'taux'                   => 'taux',
        'montant'                => 'montant',
        'montant_base'           => 'montant de base',
        'montant_fixe'           => 'montant fixe',
        'montant_paye'           => 'montant payé',
        'base_calcul'            => 'base de calcul',
        'type_impot'             => "type d'impôt",
        'delai_heures'           => 'délai en heures',
        'delai_jours'            => 'délai en jours',
        'retour_attendu'         => 'retour attendu',
        'echeance_at'            => 'échéance',
        'depose_at'              => 'date de dépôt',
        'retour_at'              => 'date de retour',
        'date_depot'             => 'date de dépôt',
        'date_retour'            => 'date de retour',
        'numero_recepisse'       => 'numéro de récépissé',
        'depend_de_bareme_id'    => 'formalité prérequise',
        'genere_formalite'       => 'génère une formalité',
        'quantite'               => 'quantité',
        'quantite_defaut'        => 'quantité par défaut',

        // Facturation
        'designation'            => 'désignation',
        'date_paiement'          => 'date du paiement',
        'moyen_paiement'         => 'moyen de paiement',

        // Courriers
        'destinataire'           => 'destinataire',
        'contenu'                => 'contenu',
        'type'                   => 'type',
        'applicable_tous'        => "applicable à tous les types d'actes",

        // Certification
        'points'                 => 'points de contrôle',

        // Paramètres de l'office
        'office_nom'             => "nom de l'office",
        'office_sous_titre'      => 'sous-titre',
        'couleur_primaire'       => 'couleur principale',
        'couleur_accent'         => "couleur d'accent",
        'couleur_fond'           => 'couleur de fond',
        'logo'                   => 'logo',
        'otp_enabled'            => 'authentification à deux facteurs',
        'otp_duration_minutes'   => 'durée de validité du code',
        'code'                   => 'code',
        'description'            => 'description',
        'ordre'                  => 'ordre',
        'est_actif'              => 'actif',
    ],

];
