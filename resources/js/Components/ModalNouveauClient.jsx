import { useEffect, useState } from 'react';
import axios from 'axios';
import { AlertCircle } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LieuSelect } from '@/components/ui/lieu-select';
import { ChampVerrouille } from '@/components/ui/champ-verrouille';
import { REGIMES_MATRIMONIAUX, SITUATIONS_MATRIMONIALES } from '@/data/questionnaires';
import { Label } from '@/components/ui/label';
import { PhoneField } from '@/components/ui/phone-field';
import { DateField } from '@/components/ui/date-field';
import { frDateToISO, isoDateToFR, isoDateSeule } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { toast } from '@/lib/toast';

const EMPTY_CLIENT = {
    type: 'physique',
    civilite: 'M.', nom_famille: '', prenoms: '', ne_a: '', date_naissance: '', nationalite: 'Guinéenne',
    situation_matrimoniale: '', regime_matrimonial: '',
    piece_type: '', piece_numero: '', piece_delivree_le: '', piece_delivree_a: '', piece_expire_le: '',
    denomination: '', forme: '', rccm: '', representant_legal: '', representant_qualite: '', siege: '',
    quartier: '', commune: '', demeurant_ville: '', pays: 'République de Guinée',
    telephone: '', email: '',
};

// Ne renvoie au serveur que les colonnes de la fiche : un client chargé depuis
// l'API porte aussi id/statut/timestamps, et les colonnes calculées ajoutées par
// ClientController::update (dossiers_mis_a_jour) — les repostrer ferait échouer la
// validation ou écraserait le statut prospect/client géré par le workflow.
// Les trois dates de la fiche, dont l'état de cette modale porte l'**ISO** (voir lib/dates.js) :
// c'est le format de leur destination, une colonne castée `date`.
const CHAMPS_DATE = ['date_naissance', 'piece_delivree_le', 'piece_expire_le'];

/**
 * Ramène une date à `AAAA-MM-JJ`, d'où qu'elle vienne — les deux sources de pré-remplissage de
 * cette modale n'emploient pas le même format :
 *   - une fiche relue depuis l'API porte un horodatage (`1985-01-04T00:00:00.000000Z`) ;
 *   - `initialValues`, construit depuis un questionnaire par `buildClientDraftFromDonnees`, porte
 *     du français (`04/01/1985`) — et le poster tel quel provoquait l'inversion jour/mois.
 * Une longueur fixe est par ailleurs nécessaire aux comparaisons de chaînes d'`incoherencesDates`.
 */
const versISO = (valeur) => isoDateSeule(valeur) || frDateToISO(valeur);

function datesEnISO(valeurs) {
    if (!valeurs) return valeurs;

    const dates = Object.fromEntries(
        CHAMPS_DATE.filter(k => k in valeurs).map(k => [k, versISO(valeurs[k])]),
    );

    return { ...valeurs, ...dates };
}

function champsFiche(client) {
    return datesEnISO(Object.fromEntries(
        Object.keys(EMPTY_CLIENT).map(k => [k, client?.[k] ?? EMPTY_CLIENT[k]]),
    ));
}

/**
 * Création rapide d'un client (personne physique ou morale) sans quitter l'assistant
 * de création de dossier. POST direct en JSON vers /clients (pas une visite Inertia) :
 * onCreated reçoit le client fraîchement créé pour l'injecter immédiatement dans le
 * formulaire appelant.
 *
 * Champs alignés sur ceux d'un gérant/associé du questionnaire (voir `clients` en base) :
 * un client créé ici porte donc déjà toutes les informations réutilisables pour
 * n'importe quel rôle (gérant, associé, vendeur…), pas seulement un sous-ensemble.
 *
 * `initialValues` (optionnel) pré-remplit le formulaire à l'ouverture — utilisé par
 * Demandes/Show.jsx pour proposer un brouillon de client à partir des données déjà
 * soumises par le client externe (voir buildClientDraftFromDonnees), à confirmer/
 * corriger avant création plutôt que de tout ressaisir.
 *
 * `client` (optionnel) fait basculer la modale en **édition** : PATCH au lieu de
 * POST. La fiche étant la source de vérité de l'identité, la corriger répercute la
 * nouvelle valeur sur les dossiers non clôturés qui la référencent — le serveur
 * renvoie la liste de ces dossiers, annoncée à l'utilisateur plutôt que passée
 * sous silence (voir ClientProjectionService).
 */
export function ModalNouveauClient({ open, onClose, onCreated, initialValues, client = null }) {
    const estEdition = !!client?.id;
    const [form, setForm] = useState({ ...EMPTY_CLIENT, ...datesEnISO(initialValues) });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!open) return;
        setForm(estEdition ? champsFiche(client) : { ...EMPTY_CLIENT, ...datesEnISO(initialValues) });
        setErrors({});
    }, [open, client?.id]);

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: e.target.value }));
    const d = (k) => (val) => setForm(p => ({ ...p, [k]: val }));
    const v = (k) => (val) => setForm(p => ({ ...p, [k]: val }));

    /** Erreur de validation renvoyée par le serveur, sous le champ concerné. */
    const erreur = (k) => errors[k] && <p className="text-xs text-danger">{errors[k][0]}</p>;

    /**
     * Changer la ville **réinitialise** commune et quartier ; changer la commune réinitialise le
     * quartier.
     *
     * Sans cela on garde une commune orpheline — « Ratoma » sous « Kindia » — c'est-à-dire
     * exactement l'incohérence que la cascade est là pour supprimer.
     */
    const changerVille = (val) => setForm(p => ({ ...p, demeurant_ville: val, commune: '', quartier: '' }));
    const changerCommune = (val) => setForm(p => ({ ...p, commune: val, quartier: '' }));

    /**
     * Un régime matrimonial n'a de sens que marié : on le vide au changement de situation, sinon la
     * valeur reste et le serveur la refuse (`prohibited_unless`) sur un champ devenu invisible.
     */
    const changerSituation = (val) => setForm(p => ({
        ...p,
        situation_matrimoniale: val,
        regime_matrimonial: val === 'Marié(e)' ? p.regime_matrimonial : '',
    }));

    /**
     * Incohérences de dates détectées **avant** l'envoi, pour un retour immédiat. Le serveur reste
     * l'autorité (`ClientController::regles()`) : ceci n'en est que le reflet.
     *
     * Comparaisons de chaînes, donc **valides uniquement en AAAA-MM-JJ** — c'est ce que garantit
     * `datesEnISO()` à l'entrée et `frDateToISO()` à la saisie. Avec du français dans l'état,
     * `'10/10/2027' < '2026-09-08'` est vrai et la pièce était annoncée expirée à tort.
     */
    const incoherencesDates = () => {
        const messages = [];
        const { date_naissance: naissance, piece_delivree_le: delivree, piece_expire_le: expire } = form;
        const aujourdhui = new Date().toISOString().slice(0, 10);

        if (naissance && naissance >= aujourdhui) messages.push('La date de naissance ne peut pas être dans le futur.');
        if (delivree && delivree > aujourdhui) messages.push("La pièce ne peut pas avoir été délivrée dans le futur.");
        if (naissance && delivree && delivree < naissance) messages.push("La pièce ne peut pas avoir été délivrée avant la naissance.");
        if (delivree && expire && expire <= delivree) messages.push("L'expiration doit être postérieure à la délivrance.");

        return messages;
    };

    /** Pièce périmée : **avertissement seul** — l'étude consigne la situation réelle du client. */
    const pieceExpiree = form.piece_expire_le && form.piece_expire_le < new Date().toISOString().slice(0, 10);

    const submit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const res = estEdition
                ? await axios.patch(`/clients/${client.id}`, form)
                : await axios.post('/clients', form);
            if (!res.data?.id) {
                // Réponse 2xx mais sans forme de client exploitable — le cas le plus
                // fréquent est une redirection silencieuse vers /login (session expirée)
                // suivie automatiquement par le navigateur : axios reçoit alors le HTML
                // de la page de connexion avec un statut 200, pas une erreur exploitable.
                const isHtml = typeof res.data === 'string' && res.data.trim().startsWith('<');
                console.error('ModalNouveauClient: réponse client inattendue', { status: res.status, data: res.data });
                toast.error(isHtml
                    ? 'Votre session a expiré. Reconnectez-vous puis réessayez de créer le client.'
                    : "Le client n'a pas pu être créé (réponse inattendue du serveur).");
                return;
            }
            // Les dossiers réalignés sont annoncés : l'utilisateur corrige une fiche
            // depuis un écran, l'effet porte potentiellement sur d'autres dossiers.
            const impactes = res.data.dossiers_mis_a_jour ?? [];
            if (impactes.length > 0) {
                toast.success(impactes.length === 1
                    ? `Fiche mise à jour — dossier ${impactes[0]} et ses actes réalignés.`
                    : `Fiche mise à jour — ${impactes.length} dossiers et leurs actes réalignés.`);
            }

            onCreated(res.data);
            if (!estEdition) setForm(EMPTY_CLIENT);
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors ?? {});
            } else {
                toast.error(err.response?.data?.message || "Impossible de créer le client — réessayez.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{estEdition ? 'Modifier la fiche client' : 'Nouveau client'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="max-h-[65vh] overflow-y-auto space-y-4 pr-1">
                        <div className="flex gap-2">
                            {['physique', 'morale'].map(t => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => setForm(p => ({ ...p, type: t }))}
                                    className={cn(
                                        'flex-1 rounded-lg border-2 px-3 py-2 text-sm font-medium transition-colors',
                                        form.type === t ? 'border-ink bg-ink/5 text-ink' : 'border-slate-200 text-slate-500 hover:border-slate-300'
                                    )}
                                >
                                    {t === 'physique' ? 'Personne physique' : 'Personne morale'}
                                </button>
                            ))}
                        </div>

                        {form.type === 'physique' ? (
                            <>
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Civilité</Label>
                                        <select value={form.civilite} onChange={f('civilite')} className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal">
                                            <option>M.</option><option>Mme</option><option>Mlle</option>
                                        </select>
                                    </div>
                                    {/* Règle 4 du CR juillet 2026 : le nom de famille doit
                                        figurer en MAJUSCULE dans les actes — impossible avec
                                        un champ unique, on ne saurait pas quelle partie du
                                        texte est le nom. */}
                                    <div className="col-span-2 space-y-1.5">
                                        <Label>Nom de famille <span className="text-danger">*</span></Label>
                                        <Input
                                            value={form.nom_famille}
                                            onChange={f('nom_famille')}
                                            placeholder="DIALLO"
                                            required
                                            className="uppercase"
                                        />
                                        <p className="text-[11px] text-slate-400">Apparaîtra en majuscules dans les actes.</p>
                                        {errors.nom_famille && <p className="text-xs text-danger">{errors.nom_famille[0]}</p>}
                                    </div>
                                    <div className="col-span-3 space-y-1.5">
                                        <Label>Prénoms</Label>
                                        <Input value={form.prenoms} onChange={f('prenoms')} placeholder="Ibrahima" />
                                        {errors.prenoms && <p className="text-xs text-danger">{errors.prenoms[0]}</p>}
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Né(e) à</Label>
                                        <Input value={form.ne_a} onChange={f('ne_a')} placeholder="Conakry" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Date de naissance</Label>
                                        <DateField value={isoDateToFR(form.date_naissance)} onValueChange={val => d('date_naissance')(frDateToISO(val))} />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Nationalité</Label>
                                        <Input value={form.nationalite} onChange={f('nationalite')} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Situation matrimoniale</Label>
                                        <select value={form.situation_matrimoniale} onChange={(e) => changerSituation(e.target.value)} className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal">
                                            <option value="">— Choisir —</option>
                                            {SITUATIONS_MATRIMONIALES.map(s => <option key={s}>{s}</option>)}
                                        </select>
                                        {erreur('situation_matrimoniale')}
                                    </div>
                                </div>
                                {/* Un régime matrimonial n'a de sens que marié : le champ disparaît
                                    sinon, plutôt que d'accepter une saisie que le serveur refuse. */}
                                {form.situation_matrimoniale === 'Marié(e)' && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="client-regime">
                                            Régime matrimonial <span className="text-danger">*</span>
                                        </Label>
                                        <select
                                            id="client-regime"
                                            value={form.regime_matrimonial}
                                            onChange={f('regime_matrimonial')}
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal"
                                        >
                                            <option value="">— Choisir —</option>
                                            {REGIMES_MATRIMONIAUX.map(r => <option key={r}>{r}</option>)}
                                            {/* Valeur héritée d'une saisie libre antérieure : gardée
                                                sélectionnée et signalée, jamais effacée en silence. */}
                                            {form.regime_matrimonial
                                                && !REGIMES_MATRIMONIAUX.includes(form.regime_matrimonial) && (
                                                <option value={form.regime_matrimonial}>
                                                    {form.regime_matrimonial} — hors liste
                                                </option>
                                            )}
                                        </select>
                                        {erreur('regime_matrimonial')}
                                        {form.regime_matrimonial
                                            && !REGIMES_MATRIMONIAUX.includes(form.regime_matrimonial) && (
                                            <p className="flex items-start gap-1 text-xs text-warning-text">
                                                <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                                                Cette valeur ne figure pas dans la liste — choisissez-en une
                                                pour pouvoir enregistrer.
                                            </p>
                                        )}
                                    </div>
                                )}
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Type de pièce</Label>
                                        <Input value={form.piece_type} onChange={f('piece_type')} placeholder="CNI CEDEAO / Passeport" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Numéro de pièce</Label>
                                        <Input value={form.piece_numero} onChange={f('piece_numero')} placeholder="GN00123456" className="font-ref" />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Pièce délivrée le</Label>
                                        <DateField value={isoDateToFR(form.piece_delivree_le)} onValueChange={val => d('piece_delivree_le')(frDateToISO(val))} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Délivrée à</Label>
                                        <Input value={form.piece_delivree_a} onChange={f('piece_delivree_a')} placeholder="Conakry" />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Pièce expire le <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                                    <DateField value={isoDateToFR(form.piece_expire_le)} onValueChange={val => d('piece_expire_le')(frDateToISO(val))} />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-1.5">
                                    <Label>Dénomination <span className="text-danger">*</span></Label>
                                    <Input value={form.denomination} onChange={f('denomination')} placeholder="Société XYZ SARL" required />
                                    {errors.denomination && <p className="text-xs text-danger">{errors.denomination[0]}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Forme juridique</Label>
                                        <Input value={form.forme} onChange={f('forme')} placeholder="SARL, SA…" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>RCCM</Label>
                                        <Input value={form.rccm} onChange={f('rccm')} placeholder="GN-CON-2020-B-XXXX" className="font-ref" />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Représentant légal</Label>
                                        <Input value={form.representant_legal} onChange={f('representant_legal')} placeholder="Ibrahima DIALLO" />
                                    </div>
                                    <div className="space-y-1.5">
                                        {/* Attendu par les modèles Word via ${bq.representant_qualite} */}
                                        <Label>Qualité du représentant</Label>
                                        <Input value={form.representant_qualite} onChange={f('representant_qualite')} placeholder="Directeur Général / Fondé de pouvoir" />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Siège social <span className="text-slate-400 text-xs">(adresse complète, optionnel)</span></Label>
                                    <Input value={form.siege} onChange={f('siege')} placeholder="Immeuble X, Almamya, Kaloum, Conakry" />
                                </div>
                            </>
                        )}

                        {/* Cascade ville → commune → quartier, dans cet ordre de saisie : une
                            commune n'a de sens que sous sa ville. En texte libre, la base a
                            accumulé « Forecariah » en commune (c'est une préfecture) et « Kountia »
                            en quartier de Conakry (il est à Dubréka). */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="client-ville">Ville</Label>
                                <LieuSelect
                                    id="client-ville"
                                    niveau="ville"
                                    value={form.demeurant_ville}
                                    onChange={changerVille}
                                    peutAjouter
                                />
                                {erreur('demeurant_ville')}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="client-commune">Commune</Label>
                                <LieuSelect
                                    id="client-commune"
                                    niveau="commune"
                                    parentNom={form.demeurant_ville}
                                    value={form.commune}
                                    onChange={changerCommune}
                                    peutAjouter
                                />
                                {erreur('commune')}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="client-quartier">Quartier</Label>
                                <LieuSelect
                                    id="client-quartier"
                                    niveau="quartier"
                                    parentNom={form.commune}
                                    value={form.quartier}
                                    onChange={v('quartier')}
                                    peutAjouter
                                />
                                {erreur('quartier')}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="client-pays">Pays</Label>
                                {/* Constante de fait sur toutes les fiches : verrouillé pour ne pas
                                    être modifié par inadvertance, mais déverrouillable — un client
                                    peut résider à l'étranger. */}
                                <ChampVerrouille
                                    id="client-pays"
                                    value={form.pays}
                                    onChange={v('pays')}
                                    placeholder="République de Guinée"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Téléphone</Label>
                                <PhoneField value={form.telephone} onValueChange={val => setForm(p => ({ ...p, telephone: val }))} placeholder="622 XX XX XX" />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Email</Label>
                                <Input type="email" value={form.email} onChange={f('email')} />
                            </div>
                        </div>
                    </div>

                    {/* Retour immédiat sur les dates : le serveur reste l'autorité, ceci n'en est
                        que le reflet — mais découvrir l'incohérence après l'envoi est inutilement
                        pénible. */}
                    {incoherencesDates().length > 0 && (
                        <div className="mx-6 mb-3 rounded-md border border-red-200 bg-danger-bg p-3">
                            <ul className="space-y-0.5 text-xs text-danger-text">
                                {incoherencesDates().map((m, i) => (
                                    <li key={i} className="flex items-start gap-1.5">
                                        <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />{m}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Pièce périmée : **avertissement seul**. L'étude doit pouvoir consigner la
                        situation réelle du client avant de lui demander un renouvellement. */}
                    {pieceExpiree && incoherencesDates().length === 0 && (
                        <p className="mx-6 mb-3 flex items-start gap-1.5 rounded-md border border-amber-200 bg-warning-bg p-3 text-xs text-warning-text">
                            <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                            Cette pièce d'identité est expirée. La fiche s'enregistre — pensez à demander
                            un renouvellement avant la signature de l'acte.
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" disabled={submitting || incoherencesDates().length > 0}>{submitting ? 'Création…' : 'Créer le client'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
