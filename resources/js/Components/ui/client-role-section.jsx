import { useState } from 'react';
import { Pencil, RefreshCw, Users, IdCard, MapPin, Phone, Mail, PenLine, X, AlertTriangle } from 'lucide-react';
import { ClientPicker } from '@/Components/ui/client-picker';
import { clientDisplayName, adresseComposite } from '@/lib/clientFields';
import { cn } from '@/lib/utils';

/**
 * Rattachement d'un rôle de l'acte (associé unique, gérant, vendeur…) à une fiche
 * client.
 *
 * Avant cette refonte, sélectionner un client **recopiait** son identité dans les
 * 18 champs texte de la section : l'utilisateur avait créé la fiche en haut du
 * formulaire, puis retrouvait le même formulaire d'identité complet en bas. Et la
 * copie divergeait dès la première correction (un numéro de CNI corrigé sur la
 * fiche ne remontait dans aucun dossier).
 *
 * Ici la section ne fait que **désigner** une fiche : les champs d'identité
 * disparaissent de l'écran, remplacés par une carte de synthèse. La fiche reste la
 * source de vérité ; le questionnaire est reprojeté depuis elle côté serveur (voir
 * app/Services/ClientProjectionService.php).
 *
 * Échappatoire : « Saisir sans fiche client » restitue les champs en texte libre,
 * pour un tiers ponctuel qu'on ne veut pas verser au répertoire.
 */
export function ClientRoleSection({
    roleLabel,
    linked,
    onSelect,
    onUnlink,
    onCreateNew,
    onEditClient,
    poolClients = [],
    champsManquants = [],
    saisieLibre = false,
    onToggleSaisieLibre,
    readOnly = false,
    children,
}) {
    const [changement, setChangement] = useState(false);

    // Saisie libre : la section redevient un formulaire classique, avec un rappel
    // de ce qu'on perd (pas de réutilisation, pas de mise à jour automatique).
    if (saisieLibre && !linked) {
        return (
            <div className="space-y-3">
                <div className="flex items-start justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2">
                    <p className="text-xs text-slate-500">
                        <PenLine className="mr-1 inline h-3 w-3 align-[-2px]" />
                        Saisie libre — cette personne ne sera pas versée au répertoire et ses
                        informations ne seront pas réutilisables sur un autre dossier.
                    </p>
                    {!readOnly && (
                        <button
                            type="button"
                            onClick={() => onToggleSaisieLibre?.(false)}
                            className="shrink-0 text-xs font-medium text-seal hover:underline"
                        >
                            Utiliser une fiche client
                        </button>
                    )}
                </div>
                {children}
            </div>
        );
    }

    if (linked && !changement) {
        return (
            <div className="space-y-3">
                <FicheLiee
                    client={linked}
                    roleLabel={roleLabel}
                    readOnly={readOnly}
                    champsManquants={champsManquants}
                    onEdit={() => onEditClient?.(linked)}
                    onChanger={() => setChangement(true)}
                    onUnlink={onUnlink}
                />
                {/* Seules les données propres à l'acte restent saisissables ici
                    (nombre de parts, fonction…) — l'identité vient de la fiche. */}
                {children}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-3">
                <p className="mb-2 text-xs text-slate-500">
                    {roleLabel
                        ? <>Désignez la personne qui tient le rôle de <span className="font-medium text-slate-700">{roleLabel.toLowerCase()}</span> — choisissez-la parmi les clients du dossier, cherchez-la dans le répertoire, ou créez sa fiche.</>
                        : 'Choisissez la personne parmi les clients du dossier, le répertoire, ou créez sa fiche.'}
                </p>
                <ClientPicker
                    placeholder={roleLabel ? `Rechercher un client existant (${roleLabel})…` : 'Rechercher un client existant…'}
                    linked={null}
                    onSelect={(client) => { onSelect?.(client); setChangement(false); }}
                    onUnlink={onUnlink}
                    onCreateNew={onCreateNew}
                    poolClients={poolClients}
                />
                <div className="mt-2 flex items-center justify-between gap-3">
                    {!readOnly && onToggleSaisieLibre && (
                        <button
                            type="button"
                            onClick={() => onToggleSaisieLibre(true)}
                            className="text-xs text-slate-400 hover:text-slate-600 hover:underline"
                        >
                            Saisir sans fiche client
                        </button>
                    )}
                    {changement && (
                        <button
                            type="button"
                            onClick={() => setChangement(false)}
                            className="text-xs text-slate-400 hover:text-slate-600 hover:underline"
                        >
                            Annuler le changement
                        </button>
                    )}
                </div>
            </div>
            {/* En mode "changement de client", les champs propres à l'acte restent
                visibles : on remplace la personne, pas ses parts ni sa fonction. */}
            {changement && children}
        </div>
    );
}

function LigneInfo({ icon: Icon, children }) {
    if (!children) return null;
    return (
        <span className="inline-flex items-center gap-1 text-xs text-slate-500">
            <Icon className="h-3 w-3 shrink-0 text-slate-400" />
            {children}
        </span>
    );
}

/**
 * Carte de synthèse d'une fiche rattachée. Volontairement en lecture : on ne
 * réaffiche pas 18 champs, mais on montre assez pour vérifier d'un coup d'œil
 * qu'on a désigné la bonne personne (nom, pièce, résidence, contact).
 */
function FicheLiee({ client, roleLabel, readOnly, champsManquants = [], onEdit, onChanger, onUnlink }) {
    const estMorale = client.type === 'morale';
    const piece = estMorale
        ? [client.forme, client.rccm].filter(Boolean).join(' · ')
        : [client.piece_type, client.piece_numero].filter(Boolean).join(' ');
    const adresse = client.siege || adresseComposite(client);

    return (
        <div className="rounded-lg border border-seal/30 bg-seal-light/60 p-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                        <Users className="h-3.5 w-3.5 shrink-0 text-seal-hover" />
                        <span className="truncate text-sm font-semibold text-ink">
                            {clientDisplayName(client) || 'Client sans nom'}
                        </span>
                        <span className={cn(
                            'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                            estMorale ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-600',
                        )}>
                            {estMorale ? 'Personne morale' : 'Personne physique'}
                        </span>
                    </div>
                    <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                        <LigneInfo icon={IdCard}>{piece || null}</LigneInfo>
                        <LigneInfo icon={MapPin}>{adresse || null}</LigneInfo>
                        <LigneInfo icon={Phone}>{client.telephone || null}</LigneInfo>
                        <LigneInfo icon={Mail}>{client.email || null}</LigneInfo>
                    </div>
                    {estMorale && client.representant_legal && (
                        <p className="mt-1 text-xs text-slate-500">
                            Représenté par {client.representant_legal}
                            {client.representant_qualite ? `, ${client.representant_qualite}` : ''}
                        </p>
                    )}
                </div>

                {!readOnly && (
                    <div className="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            onClick={onEdit}
                            title="Modifier la fiche client"
                            className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs text-slate-500 transition-colors hover:bg-white hover:text-seal"
                        >
                            <Pencil className="h-3 w-3" /> Modifier la fiche
                        </button>
                        <button
                            type="button"
                            onClick={onChanger}
                            title="Désigner une autre personne pour ce rôle"
                            className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs text-slate-500 transition-colors hover:bg-white hover:text-seal"
                        >
                            <RefreshCw className="h-3 w-3" /> Changer
                        </button>
                        {onUnlink && (
                            <button
                                type="button"
                                onClick={onUnlink}
                                title="Retirer le lien client"
                                className="rounded-md p-1 text-slate-400 transition-colors hover:bg-white hover:text-danger"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                )}
            </div>

            {champsManquants.length > 0 ? (
                /* Le champ est masqué (porté par la fiche) mais reste exigé par l'acte :
                   sans ce rappel, « Continuer » resterait inactif sans explication. */
                <div className="mt-2 flex items-start gap-2 rounded-md border border-warning/30 bg-warning-bg px-2.5 py-2">
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-warning-text" />
                    <p className="text-[11px] text-warning-text">
                        Fiche incomplète pour cet acte — il manque :{' '}
                        <span className="font-medium">{champsManquants.join(', ')}</span>.
                        {!readOnly && (
                            <button type="button" onClick={onEdit} className="ml-1 font-semibold underline">
                                Compléter la fiche
                            </button>
                        )}
                    </p>
                </div>
            ) : roleLabel && (
                <p className="mt-2 border-t border-seal/20 pt-2 text-[11px] text-slate-500">
                    Les informations d'identité de cette personne proviennent de sa fiche client et
                    seront reportées automatiquement dans les actes. Corrigez-les via « Modifier la fiche ».
                </p>
            )}
        </div>
    );
}
