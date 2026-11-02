# -*- coding: utf-8 -*-
import io

p = 'resources/js/data/questionnaires.js'
s = io.open(p, encoding='utf-8').read()

# ── 1. Le gérant actuel : une liste, pas une zone de texte ──────────────
OLD = "        { id: 'soc.gerant_actuel', label: 'Gérant / dirigeant actuel', type: 'text', placeholder: 'Ibrahima DIALLO', required: false, publicIntake: true },"
NEW = """        // ⚠️ **Prérempli depuis le registre mais resté modifiable, et c'était documenté** :
        // `estChampSociete()` l'exclut volontairement des champs masqués, parce que le
        // dirigeant était *calculé* (json `direction`, parties du dossier d'origine) et pouvait
        // être périmé ou inconnu.
        //
        // La première raison a vieilli : depuis le lot 1, `societe_gerants` tient les mandats
        // et `EtatSocieteService::courant()` rend les gérants en fonction. La seconde reste
        // vraie — d'où une **liste** plutôt qu'un verrou, avec l'échappement « Saisir un autre
        // nom » pour une société dont le registre ignore le dirigeant.
        { id: 'soc.gerant_actuel', label: 'Gérant / dirigeant actuel', type: 'select', optionsDe: 'gerantsEnFonction',
          placeholder: 'Ibrahima DIALLO', required: false, publicIntake: true, saisieLibreAutorisee: true },"""
assert s.count(OLD) == 1, 'ancre soc.gerant_actuel'
s = s.replace(OLD, NEW)

# ── 2. La note du mode de traitement, raccourcie ────────────────────────
OLD = """          note: "Choix enregistré et repris à l'acte. Les pièces et les actes propres au dépôt "
              + "au rang des minutes restent à câbler : les modèles de dissolution et de "
              + 'liquidation sont encore en cours de numérisation.' },"""
NEW = """          // ⚠️ Note volontairement **courte** : la première version tenait trois lignes et
          // occupait plus de place que le champ lui-même, au point de repousser « Gérant
          // actuel » hors de l'écran. Le détail — gabarits de dépôt au rang des minutes en
          // cours de numérisation — vit dans `App\\Enums\\ModeTraitement`, pas sous le select.
          note: "Les actes propres au dépôt au rang des minutes restent à câbler." },"""
assert s.count(OLD) == 1, 'ancre note mode_traitement'
n = s.count(OLD)
s = s.replace(OLD, NEW)

io.open(p, 'w', encoding='utf-8').write(s)
print('ok : gérant actuel en liste, note raccourcie (%d occurrence)' % n)
