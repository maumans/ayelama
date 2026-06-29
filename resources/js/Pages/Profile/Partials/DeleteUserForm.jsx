import { useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Trash2, AlertTriangle } from 'lucide-react';

export default function DeleteUserForm() {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const passwordInput = useRef();

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const closeModal = () => {
        setConfirmOpen(false);
        clearErrors();
        reset();
    };

    const deleteUser = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <>
        <Card className="border-red-100">
            <CardHeader>
                <div className="flex items-center gap-3">
                    <div className="h-9 w-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                        <Trash2 className="h-4 w-4 text-red-500" />
                    </div>
                    <div>
                        <CardTitle className="font-serif text-base text-red-700">Supprimer le compte</CardTitle>
                        <CardDescription className="text-xs">
                            Cette action est définitive et irréversible.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-slate-500 mb-4">
                    Une fois votre compte supprimé, toutes vos données personnelles seront effacées
                    définitivement. Assurez-vous d'avoir sauvegardé les informations utiles avant de procéder.
                </p>
                <Button variant="destructive" onClick={() => setConfirmOpen(true)}>
                    Supprimer mon compte
                </Button>
            </CardContent>
        </Card>

        <Dialog open={confirmOpen} onOpenChange={closeModal}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <div className="flex items-start gap-3">
                        <div className="shrink-0 h-9 w-9 rounded-full bg-red-100 flex items-center justify-center mt-0.5">
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </div>
                        <div>
                            <DialogTitle className="text-base">Supprimer votre compte ?</DialogTitle>
                            <DialogDescription className="mt-1 text-sm">
                                Cette action est irréversible. Saisissez votre mot de passe pour confirmer.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={deleteUser} className="mt-2 space-y-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="del_password">Mot de passe</Label>
                        <Input
                            id="del_password"
                            ref={passwordInput}
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoFocus
                            placeholder="Votre mot de passe actuel"
                        />
                        {errors.password && <p className="text-xs text-red-600">{errors.password}</p>}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={closeModal}>
                            Annuler
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {processing ? 'Suppression…' : 'Supprimer définitivement'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        </>
    );
}
