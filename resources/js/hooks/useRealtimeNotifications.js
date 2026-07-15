import { useEffect } from 'react';
import { toast } from '@/lib/toast';

/**
 * Écoute le canal privé Pusher de l'utilisateur pour recevoir les notifications
 * en temps réel (sans recharger la page). Ne fait rien si Echo n'est pas
 * configuré (VITE_PUSHER_APP_KEY absent — voir resources/js/bootstrap.js).
 */
export function useRealtimeNotifications(userId, onNotification) {
    useEffect(() => {
        if (!userId || !window.Echo) return;

        const channel = window.Echo.private(`App.Models.User.${userId}`);

        const handler = (notification) => {
            toast.info(notification.message ?? 'Nouvelle notification');

            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                new Notification(notification.message ?? 'Ayelema', { icon: '/favicon.ico' });
            }

            onNotification?.(notification);
        };

        channel.notification(handler);

        return () => {
            window.Echo.leave(`App.Models.User.${userId}`);
        };
    }, [userId]);
}
