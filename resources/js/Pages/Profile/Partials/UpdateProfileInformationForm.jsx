import { Link, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { User, CheckCircle } from 'lucide-react';

export default function UpdateProfileInformationForm({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-3">
                    <div className="h-9 w-9 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                        <User className="h-4 w-4 text-slate-500" />
                    </div>
                    <div>
                        <CardTitle className="font-serif text-base">Informations du profil</CardTitle>
                        <CardDescription className="text-xs">
                            Mettez à jour votre nom et votre adresse e-mail.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Nom complet</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="name"
                        />
                        {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="email">Adresse e-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                        />
                        {errors.email && <p className="text-xs text-red-600">{errors.email}</p>}
                    </div>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                            <p>Votre adresse e-mail n'est pas encore vérifiée.</p>
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="mt-1 font-medium underline hover:no-underline"
                            >
                                Renvoyer l'e-mail de vérification
                            </Link>
                            {status === 'verification-link-sent' && (
                                <p className="mt-1 font-medium text-green-700">
                                    Un nouvel e-mail de vérification a été envoyé.
                                </p>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-3 pt-1">
                        <Button type="submit" variant="seal" disabled={processing}>
                            Enregistrer les modifications
                        </Button>
                        {recentlySuccessful && (
                            <span className="flex items-center gap-1.5 text-sm text-green-600">
                                <CheckCircle className="h-4 w-4" />
                                Enregistré
                            </span>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
