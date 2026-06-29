import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Mot de passe oublié — Ayelema" />

            <div className="space-y-1">
                <h1 className="font-serif text-2xl text-ink font-semibold">Mot de passe oublié</h1>
                <p className="text-sm text-slate-500">
                    Saisissez votre adresse e-mail et nous vous enverrons un lien de réinitialisation.
                </p>
            </div>

            {status && (
                <div className="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="space-y-1.5">
                    <Label htmlFor="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
                        placeholder="vous@email.com"
                    />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <Button type="submit" variant="seal" className="w-full" disabled={processing}>
                    {processing ? 'Envoi…' : 'Envoyer le lien de réinitialisation'}
                </Button>

                <p className="text-center text-sm text-slate-500">
                    <Link href={route('login')} className="text-seal hover:underline">
                        ← Retour à la connexion
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
