import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ShieldCheck } from 'lucide-react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmer l'accès — Ayelema" />

            <div className="space-y-1">
                <div className="flex items-center gap-2 mb-3">
                    <div className="h-9 w-9 rounded-lg bg-amber-50 flex items-center justify-center">
                        <ShieldCheck className="h-5 w-5 text-amber-600" />
                    </div>
                </div>
                <h1 className="font-serif text-2xl text-ink font-semibold">Zone sécurisée</h1>
                <p className="text-sm text-slate-500">
                    Veuillez confirmer votre mot de passe avant de continuer vers cette section protégée.
                </p>
            </div>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="space-y-1.5">
                    <Label htmlFor="password">Mot de passe</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoFocus
                        autoComplete="current-password"
                    />
                    {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                </div>

                <Button type="submit" variant="seal" className="w-full" disabled={processing}>
                    {processing ? 'Vérification…' : 'Confirmer'}
                </Button>
            </form>
        </GuestLayout>
    );
}
