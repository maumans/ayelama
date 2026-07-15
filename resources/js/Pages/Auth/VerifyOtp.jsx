import { useEffect, useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ShieldCheck } from 'lucide-react';

function formatMmSs(totalSeconds) {
    const m = Math.floor(totalSeconds / 60);
    const s = totalSeconds % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

export default function VerifyOtp({ email, expiresInSeconds, status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        remember_device: false,
    });

    const [secondsLeft, setSecondsLeft] = useState(expiresInSeconds);
    const [resendCooldown, setResendCooldown] = useState(0);

    useEffect(() => {
        if (secondsLeft <= 0) return;
        const id = setInterval(() => setSecondsLeft((s) => Math.max(0, s - 1)), 1000);
        return () => clearInterval(id);
    }, [secondsLeft > 0]);

    useEffect(() => {
        if (resendCooldown <= 0) return;
        const id = setInterval(() => setResendCooldown((s) => Math.max(0, s - 1)), 1000);
        return () => clearInterval(id);
    }, [resendCooldown > 0]);

    const expired = secondsLeft <= 0;

    const submit = (e) => {
        e.preventDefault();
        post(route('two-factor.verify'), {
            onFinish: () => reset('code'),
        });
    };

    const resend = () => {
        post(route('two-factor.resend'), {
            preserveScroll: true,
            onSuccess: () => {
                setSecondsLeft(expiresInSeconds);
                setResendCooldown(60);
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Vérification — Ayelema" />

            <div className="space-y-1">
                <div className="flex items-center gap-2 mb-3">
                    <div className="h-9 w-9 rounded-lg bg-seal-light flex items-center justify-center">
                        <ShieldCheck className="h-5 w-5 text-seal" />
                    </div>
                </div>
                <h1 className="font-serif text-2xl text-ink font-semibold">Vérification en deux étapes</h1>
                <p className="text-sm text-slate-500">
                    Un code à 6 chiffres a été envoyé à <span className="font-medium text-ink">{email}</span>.
                </p>
            </div>

            {status === 'otp-resent' && (
                <p className="mt-4 text-sm text-success bg-green-50 rounded-md px-3 py-2">
                    Un nouveau code vous a été envoyé.
                </p>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="space-y-1.5">
                    <Label htmlFor="code">Code de vérification</Label>
                    <Input
                        id="code"
                        inputMode="numeric"
                        maxLength={6}
                        autoFocus
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                        className="text-center text-2xl tracking-[0.5em] font-ref"
                        placeholder="------"
                    />
                    {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code}</p>}
                    <p className={`text-xs ${expired ? 'text-red-600' : 'text-slate-400'}`}>
                        {expired ? 'Code expiré, demandez-en un nouveau.' : `Expire dans ${formatMmSs(secondsLeft)}`}
                    </p>
                </div>

                <label className="flex items-center gap-2 text-sm cursor-pointer">
                    <Checkbox
                        checked={data.remember_device}
                        onCheckedChange={(checked) => setData('remember_device', checked === true)}
                    />
                    Se souvenir de cet appareil pendant 30 jours
                </label>

                <Button type="submit" variant="seal" className="w-full" disabled={processing || expired || data.code.length !== 6}>
                    {processing ? 'Vérification…' : 'Valider'}
                </Button>

                <div className="flex items-center justify-between text-sm">
                    <Link href={route('login')} className="text-slate-500 hover:underline">
                        Retour à la connexion
                    </Link>
                    <button
                        type="button"
                        onClick={resend}
                        disabled={resendCooldown > 0}
                        className="text-seal hover:underline font-medium disabled:text-slate-400 disabled:no-underline"
                    >
                        {resendCooldown > 0 ? `Renvoyer (${resendCooldown}s)` : 'Renvoyer le code'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
