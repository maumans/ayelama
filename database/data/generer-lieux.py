"""
Génère database/data/lieux-guinee.json depuis le dump GeoNames GN.zip.

Reproductible : télécharger https://download.geonames.org/export/dump/GN.zip puis relancer.
Licence des données : CC-BY 4.0 (attribution GeoNames).
"""
import collections
import io
import json
import sys
import unicodedata
import zipfile

GN = sys.argv[1] if len(sys.argv) > 1 else 'GN.zip'
SORTIE = sys.argv[2] if len(sys.argv) > 2 else 'lieux-guinee.json'
# Orthographes francaises de reference, exportees depuis la base (les 34 prefectures y sont
# deja correctement accentuees). GeoNames nomme ses ADM2 en anglais non accentue -- Boke,
# Kerouane, Telimele -- ce qui partirait tel quel dans un acte notarial.
REFERENCE = sys.argv[3] if len(sys.argv) > 3 else 'villes-en-base.json'

# ── Préfectures créées après le dump GeoNames (qui n'en porte que 34) ────────────────────
# Source : fr.wikipedia.org/wiki/Subdivision_de_la_Guinée (énumération par région).
# ⚠️ La même page annonce 44 préfectures ailleurs dans son texte tout en n'en énumérant que 39 :
# on n'ajoute que celles qui sont **nommées**, jamais un décompte.
PREFECTURES_AJOUTEES = {
    'Kamsar': 'Boké',
    'Timbo': 'Mamou',
    'Tokounou': 'Kankan',
    'Dialakoro': 'Kankan',
    'Sabadou Baranama': 'Kankan',
}

# ── Conakry : 13 communes depuis la loi L2024/003/CNT du 18 janvier 2024 ────────────────
# GeoNames n'en porte que 5 (découpage antérieur à la réforme).
CONAKRY_COMMUNES = [
    'Kaloum', 'Dixinn', 'Matam', 'Ratoma', 'Matoto',
    'Gbessia', 'Lambanyi', 'Sonfonia', 'Tombolia',
    'Kagbelen', 'Sanoyah', 'Manéah', 'Kassa',
]

SUFFIXES = (' Sub-Prefecture', ' Prefecture', ' Special Zone', ' Region')

# GeoNames melange l anglais et le francais : trois ADM2 portent un **prefixe** francais
# (« Préfecture de Dubréka ») là où les autres portent un suffixe anglais. Ne nettoyer que les
# suffixes creait trois prefectures en double — detecte par le dry-run de la commande d import.
PREFIXES = ('Préfecture de ', 'Préfecture du ', 'Prefecture de ', 'Zone spéciale de ')

# Orthographes qui diffèrent au-delà des accents, donc que `comparable()` ne rapproche pas.
# La forme retenue est celle **déjà en base**, puisque c’est elle qui figure dans les actes.
ALIAS = {
    'guekedou': 'Guéckédou',
}


def comparable(valeur):
    """Miroir de App\\Support\\Normalisation::comparable() — accents retirés, minuscules."""
    sans_accents = unicodedata.normalize('NFD', valeur)
    sans_accents = ''.join(c for c in sans_accents if unicodedata.category(c) != 'Mn')
    return ' '.join(sans_accents.lower().split())


def nettoyer(nom):
    for p in PREFIXES:
        if nom.startswith(p):
            nom = nom[len(p):]
    for s in SUFFIXES:
        if nom.endswith(s):
            nom = nom[: -len(s)]
    return nom.strip()


def lire(chemin):
    z = zipfile.ZipFile(chemin)
    for ligne in io.TextIOWrapper(z.open('GN.txt'), encoding='utf-8'):
        colonnes = ligne.rstrip('\n').split('\t')
        if len(colonnes) >= 13:
            yield {
                'nom': colonnes[1],
                'code': colonnes[7],
                'a1': colonnes[10],
                'a2': colonnes[11],
            }


def main():
    orthographes = {
        comparable(n): n for n in json.load(io.open(REFERENCE, encoding='utf-8'))
    }

    adm2, adm3 = {}, []

    for r in lire(GN):
        if r['code'] == 'ADM2':
            nom = nettoyer(r['nom'])
            cle = comparable(nom)
            adm2[(r['a1'], r['a2'])] = orthographes.get(cle, ALIAS.get(cle, nom))
        elif r['code'] == 'ADM3':
            adm3.append(r)

    # préfecture → communes rurales (les sous-préfectures du dump)
    communes = collections.defaultdict(set)
    orphelines = []

    for r in adm3:
        parent = adm2.get((r['a1'], r['a2']))
        nom = nettoyer(r['nom'])

        if parent is None:
            orphelines.append(nom)
            continue

        communes[parent].add(nom)

    prefectures = sorted(adm2.values())

    # Le chef-lieu est la **commune urbaine** de la préfecture : GeoNames ne l'inscrit pas comme
    # ADM3, il faut donc l'ajouter — sans quoi « Boké » n'aurait aucune commune à son nom.
    for p in prefectures:
        if comparable(p) != 'conakry':
            communes[p].add(p)

    for nouvelle, parent in PREFECTURES_AJOUTEES.items():
        prefectures.append(nouvelle)
        communes[nouvelle].add(nouvelle)

    # Conakry : la réforme prime sur le dump.
    conakry = next((p for p in prefectures if comparable(p) == 'conakry'), 'Conakry')
    communes[conakry] = set(CONAKRY_COMMUNES)

    donnees = {
        'source': 'geonames GN.zip (CC-BY 4.0) + loi L2024/003/CNT du 18/01/2024 + '
                  'fr.wikipedia.org/wiki/Subdivision_de_la_Guinée',
        'genere_le': '2026-09-10',
        'note': "Les quartiers ne figurent pas ici : aucune source exploitable ne les couvre au "
                "niveau national (4 142 districts/quartiers, OCHA/HDX ne descend à ce niveau que "
                "pour Conakry). Ils se peuplent par l'usage, via le bouton d'ajout de la cascade.",
        'villes': [
            {'nom': p, 'communes': sorted(communes[p], key=comparable)}
            for p in sorted(set(prefectures), key=comparable)
        ],
    }

    with io.open(SORTIE, 'w', encoding='utf-8', newline='\n') as f:
        json.dump(donnees, f, ensure_ascii=False, indent=2)
        f.write('\n')

    total = sum(len(v['communes']) for v in donnees['villes'])
    print('villes   :', len(donnees['villes']))
    print('communes :', total)
    print('orphelines (non rattachees, ignorees) :', len(orphelines), orphelines)


if __name__ == '__main__':
    main()
