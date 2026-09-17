import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Gift, Calendar, Mail, Phone, Clock } from 'lucide-react';

export default function Index({ clients }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Anniversaires' }]}>
            <div className="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
                            <Gift className="w-6 h-6 text-pink-500" />
                            Anniversaires à venir
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Liste des clients célébrant leur anniversaire dans les 30 prochains jours.
                        </p>
                    </div>
                    <div className="text-sm text-slate-500 bg-white px-4 py-2 rounded-md shadow-sm border border-slate-200">
                        <span className="font-semibold text-slate-700">{clients.length}</span> anniversaire(s) à venir
                    </div>
                </div>

                <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                    {clients.length === 0 ? (
                        <div className="p-12 text-center">
                            <Gift className="w-12 h-12 text-slate-300 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-slate-900">Aucun anniversaire</h3>
                            <p className="text-slate-500 mt-1">
                                Aucun client n'a son anniversaire prévu dans les 30 prochains jours.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            Client
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            Contact
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            Date de naissance
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            Prochain anniversaire
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            Dans
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-slate-200">
                                    {clients.map((client) => {
                                        const isToday = client.jours_restants === 0;
                                        const isTomorrow = client.jours_restants === 1;
                                        
                                        return (
                                            <tr key={client.id} className={isToday ? 'bg-pink-50/50' : 'hover:bg-slate-50 transition-colors'}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-10 w-10 bg-slate-100 rounded-full flex items-center justify-center border border-slate-200">
                                                            <span className="text-slate-600 font-medium text-sm">
                                                                {client.nom.substring(0, 2).toUpperCase()}
                                                            </span>
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-slate-900 flex items-center gap-1.5">
                                                                {client.nom}
                                                                {isToday && (
                                                                    <Gift className="w-4 h-4 text-pink-500" />
                                                                )}
                                                            </div>
                                                            <div className="text-sm text-slate-500">
                                                                Aura {client.age_a_venir} ans
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900 flex items-center gap-1">
                                                        <Mail className="w-3.5 h-3.5 text-slate-400" />
                                                        {client.email || <span className="text-slate-400 italic">Non renseigné</span>}
                                                    </div>
                                                    <div className="text-sm text-slate-500 flex items-center gap-1 mt-1">
                                                        <Phone className="w-3.5 h-3.5 text-slate-400" />
                                                        {client.telephone || <span className="text-slate-400 italic">Non renseigné</span>}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900 flex items-center gap-1.5">
                                                        <Calendar className="w-4 h-4 text-slate-400" />
                                                        {new Date(client.date_naissance).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900">
                                                        {new Date(client.prochain_anniversaire).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right font-medium">
                                                    {isToday ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pink-100 text-pink-800">
                                                            Aujourd'hui !
                                                        </span>
                                                    ) : isTomorrow ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                            Demain
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center text-sm text-slate-600 gap-1">
                                                            <Clock className="w-4 h-4 text-slate-400" />
                                                            {client.jours_restants} jours
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
