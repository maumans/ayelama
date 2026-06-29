import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { MailCheck } from 'lucide-react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Vérification e-mail — Ayelema" />

            <div className="space-y-1">
                <div className="flex items-center gap-2 mb-3">
                    <div className="h-9 w-9 rounded-lg bg-blue-50 flex items-center justify-center">
                        <MailCheck className="h-5 w-5 text-blue-600" />
                    </div>
                </div>
                <h1 className="font-serif text-2xl text-ink font-semibold">Vérifiez votre e-mail</h1>
                <p className="text-sm text-slate-500">
                    Merci pour votre inscription ! Avant de commencer, veuillez vérifier votre adresse
                    e-mail en cliquant sur le lien que nous venons de vous envoyer.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    Un nouveau lien de vérification a été envoyé à l'adresse e-mail fournie lors de l'inscription.
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-3">
                <Button type="submit" variant="seal" className="w-full" disabled={processing}>
                    {processing ? 'Envoi…' : 'Renvoyer l\'e-mail de vérification'}
                </Button>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="w-full flex items-center justify-center rounded-md border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors"
                >
                    Se déconnecter
                </Link>
            </form>
        </GuestLayout>
    );
}
