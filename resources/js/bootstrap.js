import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Jeton CSRF explicite pour toutes les requêtes axios.
 *
 * Il manquait : les appels axios ne reposaient que sur le cookie `XSRF-TOKEN` posé par Laravel. Dès
 * que ce cookie manquait ou avait expiré — page laissée ouverte, cookie purgé —, la requête partait
 * sans jeton, Laravel répondait 419 avec sa **page HTML** d'erreur, et l'appelant, ne trouvant pas de
 * JSON exploitable, affichait « votre session a expiré » alors que la session était parfaitement
 * valide. Constaté à la création d'une société depuis l'assistant.
 *
 * L'en-tête lu dans la balise `<meta name="csrf-token">` est stable pour la durée de la page et ne
 * dépend d'aucun cookie.
 */
const jetonCsrf = document.head.querySelector('meta[name="csrf-token"]');

if (jetonCsrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = jetonCsrf.content;
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
    });
}
