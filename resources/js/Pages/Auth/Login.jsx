import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Mail, Lock, ShieldCheck } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Connexion — Ayelema" />

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
            >
                <div className="flex items-center gap-3 mb-1">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-seal-light">
                        <ShieldCheck className="h-5 w-5 text-seal-hover" />
                    </div>
                    <div>
                        <h1 className="font-serif text-2xl text-ink font-semibold leading-tight">Connexion</h1>
                        <p className="text-sm text-slate-500">Accédez à votre espace notarial</p>
                    </div>
                </div>

                {status && (
                    <div className="mt-5 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="mt-7 space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="email">Adresse e-mail</Label>
                        <div className="relative">
                            <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                autoComplete="username"
                                autoFocus
                                placeholder="vous@email.com"
                                className="h-10 pl-9"
                            />
                        </div>
                        {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="password">Mot de passe</Label>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-medium text-seal-hover hover:underline"
                                >
                                    Mot de passe oublié ?
                                </Link>
                            )}
                        </div>
                        <div className="relative">
                            <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="current-password"
                                className="h-10 pl-9"
                            />
                        </div>
                        {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                    </div>

                    <label htmlFor="remember" className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600 select-none">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                        />
                        Se souvenir de moi
                    </label>

                    <Button type="submit" variant="seal" size="lg" className="w-full" disabled={processing}>
                        {processing ? 'Connexion…' : 'Se connecter'}
                    </Button>
                </form>
            </motion.div>
        </GuestLayout>
    );
}
