import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import PasswordRequirements from '@/Components/PasswordRequirements';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Nouveau mot de passe — Ayelema" />

            <div className="space-y-1">
                <h1 className="font-serif text-2xl text-ink font-semibold">Nouveau mot de passe</h1>
                <p className="text-sm text-slate-500">Choisissez un mot de passe sécurisé pour votre compte.</p>
            </div>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="space-y-1.5">
                    <Label htmlFor="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                    />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password">Nouveau mot de passe</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        autoFocus
                    />
                    {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                    <PasswordRequirements password={data.password} />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password_confirmation">Confirmer le mot de passe</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <p className="mt-1 text-xs text-red-600">{errors.password_confirmation}</p>
                    )}
                </div>

                <Button type="submit" variant="seal" className="w-full" disabled={processing}>
                    {processing ? 'Réinitialisation…' : 'Réinitialiser le mot de passe'}
                </Button>
            </form>
        </GuestLayout>
    );
}
