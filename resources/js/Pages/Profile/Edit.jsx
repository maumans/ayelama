import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Mon profil' }]}>
            <Head title="Mon profil — Ayelema" />

            <div className="p-6 max-w-2xl mx-auto space-y-6">
                <div>
                    <h1 className="font-serif text-display text-ink">Mon profil</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Gérez vos informations personnelles et la sécurité de votre compte.
                    </p>
                </div>

                <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                <UpdatePasswordForm />
                <DeleteUserForm />
            </div>
        </AppLayout>
    );
}
