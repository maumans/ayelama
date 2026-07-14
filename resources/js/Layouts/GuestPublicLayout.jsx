export default function GuestPublicLayout({ children, officeNom = 'Ayelema' }) {
    return (
        <div
            className="min-h-screen flex flex-col items-center justify-center p-6"
            style={{
                backgroundImage: 'radial-gradient(#E2E8F0 1px, transparent 1px)',
                backgroundSize: '24px 24px',
                backgroundColor: '#FBFAF7',
            }}
        >
            <div className="flex items-center gap-2 mb-6">
                <div className="h-9 w-9 rounded-lg bg-ink flex items-center justify-center">
                    <ScalesIcon className="h-5 w-5" />
                </div>
                <span className="font-serif text-xl text-ink">{officeNom}</span>
            </div>

            <div className="w-full max-w-[560px] rounded-2xl border border-slate-200 bg-white shadow-[0_20px_60px_-15px_rgba(15,45,96,0.15)] p-6 sm:p-8">
                {children}
            </div>

            <p className="mt-6 text-xs text-slate-400 text-center max-w-[420px]">
                Vos informations sont transmises directement à l'office notarial et ne sont
                utilisées que pour le suivi de votre dossier.
            </p>
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
