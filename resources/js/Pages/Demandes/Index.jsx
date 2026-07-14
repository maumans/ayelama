import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP } from '@/data/questionnaires';
import { groupFieldsBySection } from '@/lib/partiesPayload';
import { notifyValidationError, toast } from '@/lib/toast';
import { Link2, Plus, Copy, Trash2, ExternalLink, Mail } from 'lucide-react';
import { cn } from '@/lib/utils';

const STATUT_META = {
    en_attente: { label: 'En attente', variant: 'secondary' },
    soumise:    { label: 'Soumise',    variant: 'warning' },
    traitee:    { label: 'Traitée',    variant: 'success' },
    expiree:    { label: 'Expirée',    variant: 'destructive' },
};

function ModalGenererLien({ open, onClose, typesActes }) {
    const [categorieId, setCategorieId] = useState('');
    const [typeActeId, setTypeActeId] = useState('');
    const [clientRole, setClientRole] = useState('');
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const categories = Object.entries(typesActes ?? {});
    const typesDisponibles = categorieId ? (typesActes[categorieId] ?? []) : [];
    const typeSelected = typesDisponibles.find(t => String(t.id) === typeActeId);

    const roleOptions = (() => {
        if (!typeSelected) return [];
        const key = TYPE_ACTE_CODE_MAP[typeSelected.code];
        const questionnaire = key ? (QUESTIONNAIRES[key] ?? []) : [];
        return groupFieldsBySection(questionnaire)
            .filter(g => g.clientRole)
            .map(g => ({ value: g.clientRole, label: g.name }));
    })();

    const reset = () => {
        setCategorieId(''); setTypeActeId(''); setClientRole(''); setEmail('');
    };

    const submit = () => {
        setSubmitting(true);
        router.post('/demandes', {
            type_acte_id: typeActeId,
            client_role: clientRole || null,
            email: email || null,
        }, {
            onSuccess: () => { onClose(); reset(); },
            onError: notifyValidationError,
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) { onClose(); reset(); } }}>
            <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">Générer un lien de demande</DialogTitle>
                </DialogHeader>
                <div className="space-y-4 py-2">
                    <div className="space-y-1.5">
                        <Label>Catégorie</Label>
                        <Select value={categorieId} onValueChange={v => { setCategorieId(v); setTypeActeId(''); setClientRole(''); }}>
                            <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                            <SelectContent>
                                {categories.map(([cat]) => <SelectItem key={cat} value={cat}>{cat}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>

                    {categorieId && (
                        <div className="space-y-1.5">
                            <Label>Type d'acte</Label>
                            <Select value={typeActeId} onValueChange={v => { setTypeActeId(v); setClientRole(''); }}>
                                <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                                <SelectContent>
                                    {typesDisponibles.map(t => <SelectItem key={t.id} value={String(t.id)}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {typeSelected && roleOptions.length > 0 && (
                        <div className="space-y-1.5">
                            <Label>Ce lien concerne</Label>
                            <Select value={clientRole} onValueChange={setClientRole}>
                                <SelectTrigger><SelectValue placeholder="Choisir un rôle…" /></SelectTrigger>
                                <SelectContent>
                                    {roleOptions.map(r => <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-slate-400">
                                Le client ne verra que les champs concernant ce rôle — envoyez un lien
                                séparé à chaque personne différente si nécessaire.
                            </p>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Email du client <span className="text-slate-400 text-xs">(optionnel — pour envoi automatique)</span></Label>
                        <Input type="email" placeholder="client@exemple.com" value={email} onChange={e => setEmail(e.target.value)} />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => { onClose(); reset(); }}>Annuler</Button>
                    <Button variant="seal" onClick={submit} disabled={!typeActeId || submitting}>
                        {submitting ? 'Génération…' : 'Générer le lien'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function DemandesIndex() {
    const { demandes, typesActes, filters } = usePage().props;
    const [modalOpen, setModalOpen] = useState(false);

    const copier = (url) => {
        navigator.clipboard.writeText(url).then(() => toast.success('Lien copié dans le presse-papiers.'));
    };

    const revoquer = (id) => {
        if (!confirm('Révoquer ce lien de demande ?')) return;
        router.delete(`/demandes/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Demandes clients' }]}>
            <Head title="Demandes clients — Ayelema" />
            <div className="p-6 max-w-[1100px] mx-auto space-y-5">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">Demandes clients</h1>
                        <p className="text-slate-500 text-sm mt-1">
                            Liens externes pour laisser un client soumettre ses informations en ligne.
                        </p>
                    </div>
                    <Button variant="seal" onClick={() => setModalOpen(true)}>
                        <Plus className="h-4 w-4" /> Générer un lien
                    </Button>
                </div>

                {!demandes.data?.length ? (
                    <Card>
                        <CardContent className="p-6 text-center py-16">
                            <Link2 className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                            <p className="text-slate-500 text-sm font-medium">Aucune demande pour le moment</p>
                            <p className="text-xs text-slate-400 mt-1">Générez un lien pour laisser un client soumettre ses informations.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {demandes.data.map(d => (
                            <Card key={d.id}>
                                <CardContent className="p-4 flex items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-sm font-medium text-slate-800">{d.typeActeLabel}</span>
                                            {d.clientRole && <Badge variant="outline" className="text-[10px]">{d.clientRole}</Badge>}
                                            <Badge variant={STATUT_META[d.statut]?.variant ?? 'secondary'} className="text-[10px]">
                                                {STATUT_META[d.statut]?.label ?? d.statut}
                                            </Badge>
                                            {d.estExpiree && d.statut === 'en_attente' && (
                                                <Badge variant="destructive" className="text-[10px]">Expiré</Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-slate-400 mt-0.5">
                                            Créé par {d.creePar} le {d.created_at} · expire le {d.expire_at}
                                            {d.dossierRef && <> · dossier <span className="font-ref">{d.dossierRef}</span></>}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0">
                                        <Button variant="ghost" size="icon-sm" title="Copier le lien" onClick={() => copier(d.url)}>
                                            <Copy className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button variant="ghost" size="icon-sm" asChild title="Consulter">
                                            <a href={`/demandes/${d.id}`}>
                                                <ExternalLink className="h-3.5 w-3.5" />
                                            </a>
                                        </Button>
                                        {d.statut !== 'traitee' && (
                                            <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger" onClick={() => revoquer(d.id)} title="Révoquer">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <ModalGenererLien open={modalOpen} onClose={() => setModalOpen(false)} typesActes={typesActes} />
        </AppLayout>
    );
}
