import { useEffect, useState } from 'react';
import axios from 'axios';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneField } from '@/components/ui/phone-field';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { FORMES_SOCIETE } from '@/data/questionnaires';
import { toast } from '@/lib/toast';

const EMPTY_SOCIETE = {
    denomination: '', sigle: '', forme: '', rccm_numero: '', nif: '',
    capital_chiffres: '', nombre_parts: '', valeur_nominale_chiffres: '',
    siege_quartier: '', siege_commune: '', siege_ville: 'Conakry',
    objet_social: '', duree: '', date_constitution: '', notaire_origine: '',
    email_societe: '', telephone_societe: '',
};

/**
 * N'envoie au serveur que les colonnes de la fiche : une société chargée depuis l'API porte
 * aussi id/timestamps et les champs dérivés ajoutés par SocieteController (`nom_complet`,
 * `forme_label`, `dossier_origine`, `personnesConnues`) — les reposter ferait échouer la
 * validation. Même précaution que `champsFiche()` dans ModalNouveauClient.
 */
function champsFiche(societe) {
    return Object.fromEntries(
        Object.keys(EMPTY_SOCIETE).map(k => [k, societe?.[k] ?? EMPTY_SOCIETE[k]]),
    );
}

/**
 * Création (ou correction) d'une fiche société du registre, sans quitter l'assistant.
 *
 * Sœur de {@see ModalNouveauClient} : POST/PATCH direct en JSON vers `/societes`, `onSaved`
 * reçoit la fiche pour rattachement immédiat. Nécessaire parce que l'étude traite aussi des
 * modifications de sociétés **qu'elle n'a pas constituées** — sans ce recours, le registre
 * serait un cul-de-sac dès qu'une société n'y figure pas.
 *
 * `societe` fourni fait basculer en édition. Contrairement à la fiche client, corriger une
 * fiche société ne reprojette **rien** : la projection `soc.*` d'un dossier est figée au
 * moment du rattachement, et ne change ensuite que par une modification statutaire — laquelle
 * est l'objet d'un dossier, avec son PV et ses formalités (voir SocieteController::update).
 */
export function ModalNouvelleSociete({ open, onClose, onSaved, initialValues, societe = null }) {
    const estEdition = !!societe?.id;
    const [form, setForm] = useState({ ...EMPTY_SOCIETE, ...initialValues });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!open) return;
        setForm(estEdition ? champsFiche(societe) : { ...EMPTY_SOCIETE, ...initialValues });
        setErrors({});
    }, [open, societe?.id]);

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: e.target.value }));
    const v = (k) => (val) => setForm(p => ({ ...p, [k]: val }));

    const submit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const res = estEdition
                ? await axios.patch(`/societes/${societe.id}`, form)
                : await axios.post('/societes', form);

            if (!res.data?.id) {
                // 2xx sans fiche exploitable : le cas le plus fréquent est une redirection
                // silencieuse vers /login (session expirée) suivie par le navigateur, qui
                // renvoie le HTML de la page de connexion avec un statut 200.
                const isHtml = typeof res.data === 'string' && res.data.trim().startsWith('<');
                console.error('ModalNouvelleSociete: réponse inattendue', { status: res.status, data: res.data });
                toast.error(isHtml
                    ? 'Votre session a expiré. Reconnectez-vous puis réessayez.'
                    : "La société n'a pas pu être enregistrée (réponse inattendue du serveur).");
                return;
            }

            onSaved(res.data);
            if (!estEdition) setForm(EMPTY_SOCIETE);
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors ?? {});
            } else {
                toast.error(err.response?.data?.message || "Impossible d'enregistrer la société — réessayez.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    const erreur = (k) => errors[k] && <p className="text-xs text-danger">{errors[k][0]}</p>;

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{estEdition ? 'Modifier la fiche société' : 'Nouvelle société au registre'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="max-h-[65vh] space-y-4 overflow-y-auto pr-1">

                        <p className="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 text-xs text-slate-500">
                            Renseignez l'état de la société <strong>avant</strong> la modification. La fiche
                            servira à préremplir ce dossier et les suivants — elle sera mise à jour
                            automatiquement quand la modification deviendra effective.
                        </p>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="col-span-2 space-y-1.5">
                                <Label>Dénomination sociale <span className="text-danger">*</span></Label>
                                <Input value={form.denomination} onChange={f('denomination')} placeholder="Faya Distribution SARLU" required />
                                {erreur('denomination')}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Sigle</Label>
                                <Input value={form.sigle} onChange={f('sigle')} placeholder="FD" />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Forme juridique</Label>
                                <select
                                    value={form.forme}
                                    onChange={f('forme')}
                                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal"
                                >
                                    <option value="">— Choisir —</option>
                                    {FORMES_SOCIETE.map(o => <option key={o} value={o}>{o}</option>)}
                                </select>
                                {erreur('forme')}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Date de constitution</Label>
                                <DateField value={form.date_constitution} onValueChange={v('date_constitution')} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Numéro RCCM</Label>
                                <Input value={form.rccm_numero} onChange={f('rccm_numero')} placeholder="GN-CON-2020-B-XXXX" className="font-ref" />
                                {erreur('rccm_numero')}
                            </div>
                            <div className="space-y-1.5">
                                <Label>NIF</Label>
                                <Input value={form.nif} onChange={f('nif')} placeholder="000123456" className="font-ref" />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label>Capital (GNF)</Label>
                                <NumberField value={form.capital_chiffres} onValueChange={v('capital_chiffres')} placeholder="50 000 000" className="font-ref" />
                                {erreur('capital_chiffres')}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Nombre de parts</Label>
                                <NumberField value={form.nombre_parts} onValueChange={v('nombre_parts')} placeholder="100" className="font-ref" />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Valeur nominale</Label>
                                <NumberField value={form.valeur_nominale_chiffres} onValueChange={v('valeur_nominale_chiffres')} placeholder="500 000" className="font-ref" />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label>Quartier du siège</Label>
                                <Input value={form.siege_quartier} onChange={f('siege_quartier')} placeholder="Almamya" />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Commune</Label>
                                <Input value={form.siege_commune} onChange={f('siege_commune')} placeholder="Kaloum" />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Ville</Label>
                                <Input value={form.siege_ville} onChange={f('siege_ville')} placeholder="Conakry" />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Objet social</Label>
                            <textarea
                                rows={2}
                                value={form.objet_social}
                                onChange={f('objet_social')}
                                placeholder="Commerce général, import-export…"
                                className="w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Email</Label>
                                <Input type="email" value={form.email_societe} onChange={f('email_societe')} placeholder="contact@societe.com" />
                                {erreur('email_societe')}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Téléphone</Label>
                                <PhoneField value={form.telephone_societe} onValueChange={v('telephone_societe')} placeholder="622 XX XX XX" />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Notaire ayant constitué la société</Label>
                            <Input value={form.notaire_origine} onChange={f('notaire_origine')} placeholder="Maître … — laisser vide si constituée par un autre office inconnu" />
                        </div>

                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" variant="seal" disabled={submitting}>
                            {submitting
                                ? 'Enregistrement…'
                                : estEdition ? 'Enregistrer les modifications' : 'Ajouter au registre'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
