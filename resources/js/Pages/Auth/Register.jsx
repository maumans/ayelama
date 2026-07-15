import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import PasswordRequirements from '@/Components/PasswordRequirements';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Inscription — Ayelema" />

            <div className="space-y-1">
                <h1 className="font-serif text-2xl text-ink font-semibold">Créer un compte</h1>
                <p className="text-sm text-slate-500">Configurez votre accès à la plateforme</p>
            </div>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="space-y-1.5">
                    <Label htmlFor="name">Nom complet</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoComplete="name"
                        autoFocus
                        placeholder="Votre nom et prénom"
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        placeholder="vous@email.com"
                    />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password">Mot de passe</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
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
                    {processing ? 'Création…' : 'Créer mon compte'}
                </Button>

                <p className="text-center text-sm text-slate-500">
                    Déjà inscrit ?{' '}
                    <Link href={route('login')} className="text-seal hover:underline font-medium">
                        Se connecter
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
