import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PhoneField } from '@/components/ui/phone-field';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import PasswordRequirements, { motDePasseConforme } from '@/Components/PasswordRequirements';
import { notifyValidationError } from '@/lib/toast';

const VIDE = { name: '', email: '', password: '', roles: [], initiales: '', telephone: '' };

/**
 * Créer ou modifier un compte — **une seule** fenêtre pour les deux écrans qui le font
 * (`Parametres/Index`, onglet Utilisateurs, et `Parametres/Utilisateurs`).
 *
 * Signalé le 2026-11-19 : « je n'arrive pas à ajouter un utilisateur ». Le mot de passe saisi
 * faisait 8 caractères ; la politique (`Password::defaults()`, AppServiceProvider) en exige 12,
 * avec majuscule, minuscule, chiffre et symbole. Le serveur refusait — et **rien ne
 * s'affichait** : aucune des deux copies de cette fenêtre n'avait d'`onError`, et l'onglet de
 * `Parametres/Index` ne montrait même pas les exigences. Le clic sur « Créer » ne faisait
 * visiblement rien. Les deux copies avaient déjà divergé (exigences présentes dans l'une
 * seulement) : d'où ce composant partagé.
 *
 * Les exigences sont vérifiées avant l'envoi, et toute erreur du serveur (courriel déjà pris,
 * rôle inconnu…) s'affiche sous son champ, en plus du toast.
 */
export default function ModalUtilisateur({ open, onClose, utilisateur, roles }) {
    const isEdit = Boolean(utilisateur);
    const [form, setForm] = useState(VIDE);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setErrors({});
            setForm(isEdit ? {
                name:      utilisateur.name      ?? '',
                email:     utilisateur.email     ?? '',
                password:  '',
                roles:     utilisateur.roles     ?? [],
                initiales: utilisateur.initiales ?? '',
                telephone: utilisateur.telephone ?? '',
            } : VIDE);
        }
    }, [open, utilisateur?.id]);

    const set = (k, v) => {
        setForm(p => ({ ...p, [k]: v }));
        setErrors(p => ({ ...p, [k]: undefined }));
    };

    const toggleRole = (value, checked) => {
        set('roles', checked ? [...form.roles, value] : form.roles.filter(v => v !== value));
    };

    // Un mot de passe vide est permis en modification : il conserve l'ancien.
    const motDePasseOk = (isEdit && !form.password) || motDePasseConforme(form.password);

    const submit = (e) => {
        e.preventDefault();
        if (form.roles.length === 0) { setErrors(p => ({ ...p, roles: 'Sélectionnez au moins un rôle.' })); return; }
        if (!motDePasseOk) { setErrors(p => ({ ...p, password: 'Le mot de passe ne remplit pas les exigences ci-dessous.' })); return; }

        const payload = { ...form };
        if (isEdit && !payload.password) delete payload.password;

        const opts = {
            preserveState: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errs) => { setErrors(errs); notifyValidationError(errs); },
            onFinish: () => setSaving(false),
        };
        setSaving(true);
        if (isEdit) router.patch(`/parametres/utilisateurs/${utilisateur.id}`, payload, opts);
        else router.post('/parametres/utilisateurs', payload, opts);
    };

    const Erreur = ({ champ }) => {
        const msg = errors[champ] ?? Object.entries(errors).find(([k]) => k.startsWith(`${champ}.`))?.[1];
        return msg ? <p className="text-xs text-danger">{msg}</p> : null;
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEdit ? "Modifier l'utilisateur" : 'Nouvel utilisateur'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2 space-y-1">
                            <Label>Nom complet</Label>
                            <Input value={form.name} onChange={e => set('name', e.target.value)} required />
                            <Erreur champ="name" />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Email</Label>
                            <Input type="email" value={form.email} onChange={e => set('email', e.target.value)} required />
                            <Erreur champ="email" />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>
                                {isEdit ? 'Nouveau mot de passe' : 'Mot de passe'}
                                {isEdit && <span className="text-slate-400 text-[11px] ml-1">(laisser vide pour conserver)</span>}
                            </Label>
                            <Input type="password" value={form.password} onChange={e => set('password', e.target.value)}
                                required={!isEdit} autoComplete="new-password" />
                            <Erreur champ="password" />
                            {(!isEdit || form.password) && <PasswordRequirements password={form.password} />}
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Rôles</Label>
                            <div className="flex flex-wrap gap-3">
                                {roles?.map(r => (
                                    <label key={r.value} className="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <Checkbox
                                            checked={form.roles.includes(r.value)}
                                            onCheckedChange={(checked) => toggleRole(r.value, checked === true)}
                                        />
                                        {r.label}
                                    </label>
                                ))}
                            </div>
                            <Erreur champ="roles" />
                        </div>
                        <div className="space-y-1">
                            <Label>Initiales</Label>
                            <Input value={form.initiales}
                                onChange={e => set('initiales', e.target.value.toUpperCase().slice(0, 3))}
                                maxLength={3} className="font-mono uppercase" placeholder="AB" />
                            <Erreur champ="initiales" />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Téléphone</Label>
                            <PhoneField value={form.telephone} onValueChange={val => set('telephone', val)} placeholder="622 XX XX XX" />
                            <Erreur champ="telephone" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onClose} disabled={saving}>Annuler</Button>
                        <Button type="submit" disabled={saving || !motDePasseOk}>
                            {saving ? 'Enregistrement…' : isEdit ? 'Enregistrer' : 'Créer'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
