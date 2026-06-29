import { Scale } from 'lucide-react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen">
            {/* Panneau gauche — identité de marque */}
            <div className="hidden lg:flex lg:w-[420px] shrink-0 bg-seal flex-col justify-between p-12 relative overflow-hidden">
                {/* Cercles décoratifs */}
                <div className="absolute top-0 right-0 h-72 w-72 rounded-full bg-white/5 -translate-y-36 translate-x-36 pointer-events-none" />
                <div className="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-white/5 translate-y-48 -translate-x-48 pointer-events-none" />

                {/* Logo */}
                <div className="relative">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-white/15 flex items-center justify-center">
                            <Scale className="h-5 w-5 text-white" />
                        </div>
                        <span className="font-serif text-2xl text-white tracking-tight">Ayelema</span>
                    </div>
                    <p className="mt-2 text-white/55 text-sm">
                        Plateforme de gestion notariale
                    </p>
                </div>

                {/* Citation */}
                <div className="relative space-y-5">
                    <div className="h-px bg-white/15" />
                    <blockquote className="text-white/65 text-sm leading-relaxed italic">
                        « Rigueur, confidentialité et efficacité au service de chaque dossier notarial. »
                    </blockquote>
                    <p className="text-white/30 text-xs">
                        © {new Date().getFullYear()} Ayelema
                    </p>
                </div>
            </div>

            {/* Panneau droit — contenu du formulaire */}
            <div className="flex-1 flex flex-col items-center justify-center bg-slate-50 p-6">
                {/* Logo visible uniquement sur mobile */}
                <div className="lg:hidden flex items-center gap-2 mb-8">
                    <div className="h-8 w-8 rounded-lg bg-seal flex items-center justify-center">
                        <Scale className="h-4 w-4 text-white" />
                    </div>
                    <span className="font-serif text-xl text-ink">Ayelema</span>
                </div>

                <div className="w-full max-w-[400px]">
                    {children}
                </div>
            </div>
        </div>
    );
}
