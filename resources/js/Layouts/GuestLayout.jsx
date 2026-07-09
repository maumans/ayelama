import { ShieldCheck, ScrollText, Landmark } from 'lucide-react';

const FEATURES = [
    { icon: ScrollText,  label: 'Conforme au droit OHADA — actes notariés sécurisés' },
    { icon: ShieldCheck, label: 'Accès contrôlé par rôle, traçabilité complète' },
    { icon: Landmark,    label: 'Suivi des formalités APIP, Impôts, Conservation, CNSS' },
];

export default function GuestLayout({ children }) {
    return (
        <div className="flex h-screen overflow-y-auto bg-app-bg">
            {/* Panneau gauche — identité de marque */}
            <div className="hidden lg:flex lg:w-[440px] shrink-0 bg-ink flex-col justify-between p-12 relative overflow-hidden">
                {/* Trame décorative — grille de points façon filigrane */}
                <div
                    className="absolute inset-0 opacity-[0.07] pointer-events-none"
                    style={{
                        backgroundImage: 'radial-gradient(rgba(255,255,255,0.9) 1px, transparent 1px)',
                        backgroundSize: '22px 22px',
                    }}
                />
                {/* Cercles décoratifs */}
                <div className="absolute top-0 right-0 h-80 w-80 rounded-full bg-seal/10 -translate-y-32 translate-x-32 pointer-events-none" />
                <div className="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-white/[0.03] translate-y-48 -translate-x-48 pointer-events-none" />
                {/* Liseré doré en haut */}
                <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-seal via-seal-hover to-seal" />

                {/* Logo */}
                <div className="relative">
                    <div className="flex items-center gap-3">
                        <div className="h-11 w-11 rounded-xl bg-seal/15 border border-seal/25 flex items-center justify-center">
                            <ScalesIcon className="h-6 w-6" />
                        </div>
                        <div>
                            <span className="font-serif text-2xl text-white tracking-tight block leading-tight">Ayelema</span>
                            <span className="text-seal text-[10px] tracking-[0.2em] uppercase font-medium">Office Notarial</span>
                        </div>
                    </div>
                    <p className="mt-3 text-white/50 text-sm max-w-[280px]">
                        Plateforme de gestion des actes notariaux — Maître Ayelama Bah, Guinée.
                    </p>
                </div>

                {/* Points forts */}
                <div className="relative space-y-4">
                    {FEATURES.map(({ icon: Icon, label }) => (
                        <div key={label} className="flex items-start gap-3">
                            <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] border border-white/10">
                                <Icon className="h-3.5 w-3.5 text-seal" />
                            </span>
                            <span className="text-sm text-white/70 leading-snug pt-1">{label}</span>
                        </div>
                    ))}
                </div>

                {/* Citation */}
                <div className="relative space-y-5">
                    <div className="h-px bg-white/10" />
                    <blockquote className="text-white/60 text-sm leading-relaxed italic">
                        « Rigueur, confidentialité et efficacité au service de chaque dossier notarial. »
                    </blockquote>
                    <p className="text-white/30 text-xs">
                        © {new Date().getFullYear()} Ayelema — Tous droits réservés
                    </p>
                </div>
            </div>

            {/* Panneau droit — contenu du formulaire */}
            <div
                className="flex-1 flex flex-col items-center justify-center p-6 relative"
                style={{
                    backgroundImage: 'radial-gradient(#E2E8F0 1px, transparent 1px)',
                    backgroundSize: '24px 24px',
                    backgroundColor: '#FBFAF7',
                }}
            >
                {/* Logo visible uniquement sur mobile */}
                <div className="lg:hidden flex items-center gap-2 mb-8">
                    <div className="h-9 w-9 rounded-lg bg-ink flex items-center justify-center">
                        <ScalesIcon className="h-5 w-5" />
                    </div>
                    <span className="font-serif text-xl text-ink">Ayelema</span>
                </div>

                <div className="w-full max-w-[420px] rounded-2xl border border-slate-200 bg-white shadow-[0_20px_60px_-15px_rgba(15,45,96,0.15)] p-8 sm:p-10">
                    {children}
                </div>

                <p className="mt-6 text-xs text-slate-400 text-center max-w-[420px]">
                    Accès réservé au personnel autorisé de l'office notarial Ayelema Bah.
                </p>
            </div>
        </div>
    );
}

function ScalesIcon({ className }) {
    return (
        <svg viewBox="0 0 40 40" className={className} fill="none">
            <path d="M8 20 Q20 6 32 20" stroke="white" strokeWidth="2.5" fill="none" strokeLinecap="round"/>
            <path d="M20 9 L22 13 L20 17 L18 13 Z" fill="#E8A520"/>
            <line x1="20" y1="13" x2="20" y2="33" stroke="white" strokeWidth="2"/>
            <line x1="8" y1="21" x2="32" y2="21" stroke="white" strokeWidth="2"/>
            <line x1="10" y1="21" x2="10" y2="27" stroke="white" strokeWidth="1.5"/>
            <line x1="30" y1="21" x2="30" y2="27" stroke="white" strokeWidth="1.5"/>
            <path d="M6 27 Q10 30.5 14 27" stroke="#E8A520" strokeWidth="2" fill="none" strokeLinecap="round"/>
            <path d="M26 27 Q30 30.5 34 27" stroke="#E8A520" strokeWidth="2" fill="none" strokeLinecap="round"/>
        </svg>
    );
}
