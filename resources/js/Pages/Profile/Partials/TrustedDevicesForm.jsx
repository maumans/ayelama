import { router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Monitor, ShieldOff } from 'lucide-react';

export default function TrustedDevicesForm({ trustedDevices = [] }) {
    const revoke = (id) => {
        router.delete(route('profile.trusted-devices.revoke', id), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-3">
                    <div className="h-9 w-9 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                        <Monitor className="h-4 w-4 text-slate-500" />
                    </div>
                    <div>
                        <CardTitle className="font-serif text-base">Appareils de confiance</CardTitle>
                        <CardDescription className="text-xs">
                            Ces appareils n'ont pas besoin de code de vérification pendant 30 jours.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {trustedDevices.length === 0 ? (
                    <p className="text-sm text-slate-400">Aucun appareil de confiance enregistré.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {trustedDevices.map((d) => (
                            <li key={d.id} className="flex items-center justify-between py-3 text-sm">
                                <div>
                                    <p className="font-medium text-ink truncate max-w-xs">{d.label}</p>
                                    <p className="text-xs text-slate-400">
                                        Utilisé {d.last_used_at ?? 'jamais'} · expire le {d.expires_at}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => revoke(d.id)}
                                    className="text-red-600 hover:text-red-700"
                                >
                                    <ShieldOff className="h-4 w-4 mr-1" />
                                    Révoquer
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
