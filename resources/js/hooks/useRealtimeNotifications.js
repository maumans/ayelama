import { useEffect, useRef } from 'react';
import { toast } from '@/lib/toast';

// Nom de l'événement diffusé par Laravel pour toute notification passant par le
// canal `broadcast` — c'est ce qu'écoute Echo.channel().notification().
const EVENEMENT_NOTIFICATION = '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';

/**
 * Écoute le canal privé Pusher de l'utilisateur pour recevoir les notifications
 * en temps réel (sans recharger la page). Ne fait rien si Echo n'est pas
 * configuré (VITE_PUSHER_APP_KEY absent — voir resources/js/bootstrap.js).
 *
 * AppLayout n'est pas un layout Inertia persistant : il remonte à chaque visite
 * de page. On ne quitte donc PAS le canal au démontage — un Echo.leave() suivi
 * d'une re-souscription immédiate ouvrait une fenêtre pendant laquelle les
 * notifications émises étaient perdues (Pusher ne rejoue rien). On retire
 * seulement le handler ; la souscription Pusher survit à la navigation.
 */
export function useRealtimeNotifications(userId, onNotification) {
    // Ref plutôt que dépendance de l'effet : le callback est recréé à chaque
    // rendu, le mettre en dépendance re-souscrirait en boucle.
    const callbackRef = useRef(onNotification);
    callbackRef.current = onNotification;

    useEffect(() => {
        if (!userId || !window.Echo) return;

        const channel = window.Echo.private(`App.Models.User.${userId}`);

        const handler = (notification) => {
            toast.info(notification.message ?? 'Nouvelle notification');

            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                new Notification(notification.message ?? 'Ayelema', { icon: '/favicon.ico' });
            }

            callbackRef.current?.(notification);
        };

        channel.notification(handler);

        return () => {
            channel.stopListening(EVENEMENT_NOTIFICATION, handler);
        };
    }, [userId]);
}
