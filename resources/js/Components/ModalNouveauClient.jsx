import { useState } from 'react';
import axios from 'axios';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneField } from '@/components/ui/phone-field';
import { cn } from '@/lib/utils';

const EMPTY_CLIENT = {
    type: 'physique',
    civilite: 'M.', prenom_nom: '', nationalite: 'Guinéenne',
    piece_type: '', piece_numero: '',
    denomination: '', forme: '', rccm: '', representant_legal: '',
    quartier: '', commune: '', demeurant_ville: '', pays: 'République de Guinée',
    telephone: '', email: '',
};

/**
 * Création rapide d'un client (personne physique ou morale) sans quitter l'assistant
 * de création de dossier. POST direct en JSON vers /clients (pas une visite Inertia) :
 * onCreated reçoit le client fraîchement créé pour l'injecter immédiatement dans le
 * formulaire appelant.
 */
export function ModalNouveauClient({ open, onClose, onCreated }) {
    const [form, setForm] = useState(EMPTY_CLIENT);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: e.target.value }));

    const submit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const res = await axios.post('/clients', form);
            onCreated(res.data);
            setForm(EMPTY_CLIENT);
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors ?? {});
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader><DialogTitle>Nouveau client</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="max-h-[60vh] overflow-y-auto space-y-4 pr-1">
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
                                    <div className="col-span-2 space-y-1.5">
                                        <Label>Nom et prénoms <span className="text-danger">*</span></Label>
                                        <Input value={form.prenom_nom} onChange={f('prenom_nom')} placeholder="Ibrahima DIALLO" required />
                                        {errors.prenom_nom && <p className="text-xs text-danger">{errors.prenom_nom[0]}</p>}
                                    </div>
                                </div>
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
                                <div className="space-y-1.5">
                                    <Label>Nationalité</Label>
                                    <Input value={form.nationalite} onChange={f('nationalite')} />
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
                                <div className="space-y-1.5">
                                    <Label>Représentant légal</Label>
                                    <Input value={form.representant_legal} onChange={f('representant_legal')} />
                                </div>
                            </>
                        )}

                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label>Quartier</Label>
                                <Input value={form.quartier} onChange={f('quartier')} />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Commune</Label>
                                <Input value={form.commune} onChange={f('commune')} />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Ville</Label>
                                <Input value={form.demeurant_ville} onChange={f('demeurant_ville')} />
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

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" disabled={submitting}>{submitting ? 'Création…' : 'Créer le client'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
