# Vérificateur de chatbot

Outil d'analyse et d'audit de conversations de chatbot, construit sur **CodeIgniter 4.7** (PHP 8.2+).

Vous lui fournissez **les conversations d'un bot** (un fichier CSV) et **sa base de connaissance** (les documents/images qu'il est censé connaître). L'outil produit trois livrables :

| Fichier | Contenu |
|---|---|
| `rapport-*.pdf` | Rapport lisible : statistiques, qualité des réponses, taux de non-réponse, fidélité à la base de connaissance |
| `audit-*.pdf` | Détail **ligne par ligne** (traçabilité complète de chaque échange) |
| `tracabilite-*.csv` | Les mêmes données d'audit, en CSV exploitable |

> ℹ️ L'outil **n'a pas d'interface graphique**. On déclenche une analyse en **ouvrant une URL** dans le navigateur. La page d'accueil affiche encore le gabarit CodeIgniter par défaut, c'est normal.

> 📖 **Doc développeur en ligne** : une fois le serveur lancé, ouvre **http://localhost:8787/docs** — endpoints, paramètres, format CSV, codes d'erreur et exemples curl / JS / Python / PHP, avec l'URL de base et la clé pré-remplies.

---

## Sommaire

1. [Prérequis](#1-prérequis)
2. [Démarrer le serveur](#2-démarrer-le-serveur)
3. [Essai immédiat (bot `demo`)](#3-essai-immédiat-bot-demo)
4. [Anatomie d'un bot](#4-anatomie-dun-bot)
5. [Ajouter votre propre bot (pas à pas)](#5-ajouter-votre-propre-bot-pas-à-pas)
6. [Lancer une analyse et ses options](#6-lancer-une-analyse-et-ses-options)
7. [Lire les résultats](#7-lire-les-résultats)
8. [Configuration (`.env`)](#8-configuration-env)
9. [Dépannage](#9-dépannage)
10. [Structure du projet](#10-structure-du-projet)

---

## 1. Prérequis

- **PHP ≥ 8.2** (testé sur 8.4). Vérifiez : `php --version`
- Les extensions CI4 habituelles (`intl`, `mbstring`, `json`, etc.).
- Les dépendances sont déjà installées dans `vendor/`. Si besoin de les réinstaller : `composer install`.

---

## 2. Démarrer le serveur

Depuis la **racine du projet** :

```bash
php spark serve --port 8787
```

Laissez cette fenêtre de terminal **ouverte** : c'est votre serveur. Il démarre sur le port `8787` (valeur de `app.baseURL` dans `.env`).

> ⚠️ **Important — utilisez `localhost`, pas `127.0.0.1`.**
> Sur certaines machines, `php spark serve` n'écoute qu'en IPv6 (`::1`). L'adresse `127.0.0.1` (IPv4) renverra alors « site inaccessible ». **`http://localhost:8787` fonctionne toujours.**
> Pour forcer l'écoute IPv4 : `php spark serve --host 0.0.0.0 --port 8787`.

---

## 3. Essai immédiat (bot `demo`)

Un bot d'exemple complet est livré dans `writable/bots/demo/`. Pour générer son rapport, ouvrez dans le navigateur :

```
http://localhost:8787/rapport/demo?key=demo-key
```

➡️ Le **PDF du rapport se télécharge**. Les trois livrables sont aussi enregistrés dans `writable/reports/demo/`.

Décomposition de l'URL :

| Partie | Rôle |
|---|---|
| `rapport/demo` | analyse le bot nommé **`demo`** |
| `?key=demo-key` | **clé API** obligatoire (valeur `REPORT_API_KEY` du `.env`) |

---

## 4. Anatomie d'un bot

Un bot = **un dossier** sous `writable/bots/`. Exemple avec `demo` :

```
writable/bots/demo/
├── conversations.csv        ← OBLIGATOIRE : les échanges du bot
└── base/                    ← OPTIONNEL : la base de connaissance
    ├── batiment.txt         (documents texte)
    ├── services.txt
    ├── transport.txt
    ├── hall_entree.png       (images référencées par le bot)
    ├── vue_cafe.jpg
    └── perspective_jardin.jpg
```

- **`conversations.csv`** : la source des échanges. Soit déposé ici (mode `GET`), soit **envoyé dans la requête** (mode `POST`, voir [§6](#envoyer-le-csv-dans-la-requête-api-post)). Si ni l'un ni l'autre → erreur 422.
- **`base/`** : facultatif mais recommandé. C'est ce qui permet de vérifier la **fidélité** des réponses (le bot répond-il conformément à ce qu'il est censé savoir ?) et l'**existence des images** citées. Sans `base/`, l'analyse fonctionne mais sans contrôle de fidélité.

---

## 5. Ajouter votre propre bot (pas à pas)

Supposons un bot nommé **`monbot`**.

### Étape 1 — Créer le dossier du bot

```bash
mkdir -p writable/bots/monbot
```

> Le nom du bot ne doit contenir que lettres, chiffres, `.`, `_`, `-` (les autres caractères sont remplacés automatiquement).

### Étape 2 — Déposer `conversations.csv`

Créez `writable/bots/monbot/conversations.csv`. **Seule la colonne `question` est obligatoire**, les autres sont optionnelles mais recommandées :

```csv
date,user_id,question,reponse,erreur
2026-05-04 09:12:00,u001,"Quels services propose le bâtiment ?","Café, restaurant, crèche, salle de sport et jardins partagés.",
2026-05-04 09:20:00,u001,"Le café est-il ouvert le week-end ?","Le café est ouvert du lundi au vendredi, de 8h à 18h.",
```

**Les noms de colonnes sont souples** — l'outil reconnaît automatiquement de nombreuses variantes :

| Champ interne | Noms acceptés dans votre CSV |
|---|---|
| `question` *(obligatoire)* | `question`, `message`, `user_message`, `input`, `demande`, `prompt`, `query`, `texte` |
| `reponse` | `reponse`, `response`, `answer`, `bot_message`, `bot_response`, `output`, `reply` |
| `date` | `date`, `datetime`, `timestamp`, `created_at`, `horodatage`, `time`, `jour` |
| `user_id` | `user_id`, `userid`, `user`, `utilisateur`, `id_utilisateur`, `session_id`, `session`, `client_id`, `distinct_id` |
| `erreur` | `erreur`, `error`, `is_error`, `has_error`, `statut_erreur` |

Bon à savoir :
- Le **séparateur** (`,` ou `;`) est détecté automatiquement.
- Les **dates** sont reconnues dans plusieurs formats (`Y-m-d H:i:s`, `d/m/Y H:i`, `Y-m-d`, etc.).
- Les guillemets `"` permettent d'inclure des virgules/sauts de ligne dans un champ.

### Étape 3 — (Recommandé) Constituer la base de connaissance

Créez `writable/bots/monbot/base/` et déposez-y ce que le bot est censé connaître :

```bash
mkdir -p writable/bots/monbot/base
```

| Type | Extensions acceptées | Usage |
|---|---|---|
| **Texte** | `.txt`, `.md` | Concaténés pour servir de référence : on vérifie que les réponses du bot **collent** à ce contenu |
| **Images** | `.png`, `.jpg`, `.jpeg`, `.webp`, `.gif` | Indexées par nom : on vérifie que les images **citées dans les réponses existent** réellement |

> La base est injectée dans l'analyse jusqu'à ~14 000 caractères (au-delà, elle est tronquée). Privilégiez des fichiers texte concis et pertinents.

### Étape 4 — Lancer

```
http://localhost:8787/rapport/monbot?key=demo-key
```

---

## 6. Lancer une analyse et ses options

Format général de l'URL :

```
http://localhost:8787/rapport/<bot>?key=<clé>[&format=json][&ai=0]
```

| Paramètre | Valeurs | Effet |
|---|---|---|
| `key` | la valeur de `REPORT_API_KEY` | **Obligatoire** si une clé est configurée. Sinon → 401 |
| `format` | `json` | Renvoie un résumé JSON au lieu de télécharger le PDF |
| `ai` | `0` | **Désactive** l'appel à l'IA (Mistral) : analyse plus rapide et sans coût, mais sans classification ni jugement de fidélité par IA |

Exemples :

```bash
# Rapport PDF complet (avec IA si une clé LLM est configurée)
http://localhost:8787/rapport/monbot?key=demo-key

# Résumé JSON
http://localhost:8787/rapport/monbot?key=demo-key&format=json

# Analyse factuelle seule, sans IA (rapide)
http://localhost:8787/rapport/monbot?key=demo-key&ai=0
```

### Envoyer le CSV dans la requête (API `POST`)

Pour intégrer l'outil depuis une autre plateforme, vous pouvez **transmettre le CSV directement dans l'appel** au lieu de le déposer sur le serveur. La route accepte alors un `POST` (les paramètres `key`, `format`, `ai` restent dans l'URL).

Deux façons d'envoyer le CSV :

**a) Upload de fichier** (multipart, champ `file` — ou `csv` / `conversations`) :

```bash
curl -X POST "http://localhost:8787/rapport/monbot?key=demo-key&format=json" \
     -F "file=@./conversations.csv"
```

**b) Corps brut** de la requête (`Content-Type: text/csv`) :

```bash
curl -X POST "http://localhost:8787/rapport/monbot?key=demo-key" \
     -H "Content-Type: text/csv" \
     --data-binary @./conversations.csv \
     -o rapport.pdf
```

> 💡 **Ordre de priorité de la source** : fichier uploadé → corps brut → fichier disque du bot. Le `GET` reste pleinement fonctionnel (lecture du fichier disque).
>
> 🧠 **Base de connaissance** : elle reste celle configurée côté serveur dans `writable/bots/<bot>/base/`. Si le bot n'existe pas encore sur le serveur, l'analyse se fait **sans contrôle de fidélité** (statistiques + qualité uniquement) — pas d'erreur 404.
>
> 📎 En-tête optionnel `X-Filename: mon-export.csv` pour nommer la source dans le rapport (corps brut uniquement).

---

## 7. Lire les résultats

Chaque analyse écrit dans `writable/reports/<bot>/` (en plus du téléchargement) :

```
rapport-<token>.pdf        # le rapport principal
audit-<token>.pdf          # l'audit détaillé ligne par ligne
tracabilite-<token>.csv    # l'audit en CSV
meta-<token>.json          # résumé machine de l'exécution
```

Le `<token>` est un horodatage (`AAAAMMJJ-HHMMSS`), donc chaque analyse crée de nouveaux fichiers sans écraser les précédents.

#### Récupérer l'audit et le CSV par URL

L'appel `/rapport/<bot>` ne renvoie que le **rapport** PDF. Pour récupérer l'**audit** ou le **CSV** du dernier run (sans tout régénérer) :

```
http://localhost:8787/telecharger/<bot>/<type>?key=<clé>
```

| `type` | Document servi |
|---|---|
| `rapport` | dernier `rapport-*.pdf` |
| `audit` | dernier `audit-*.pdf` |
| `csv` | dernier `tracabilite-*.csv` |

```bash
# l'audit du dernier rapport généré pour « demo »
http://localhost:8787/telecharger/demo/audit?key=demo-key

# le CSV de traçabilité
http://localhost:8787/telecharger/demo/csv?key=demo-key
```

> En mode JSON (`format=json`), la réponse de `/rapport/<bot>` contient déjà un bloc `download` avec ces chemins.

**Indicateurs de fidélité** (présents si une `base/` existe) :

| Étiquette | Signification |
|---|---|
| `fondee` | Réponse fondée sur la base |
| `partielle` | Partiellement fondée |
| `non_fondee` | Non fondée (contredit ou absente de la base) |
| `hors_base` | Sujet hors du périmètre de la base |
| `indetermine` | Non évalué |

**Qualité des réponses** : `coherente`, `partielle`, `non_reponse`, `hors_sujet`, `indetermine`.

---

## 8. Configuration (`.env`)

Le fichier `.env` (à la racine) contient les réglages. Valeurs livrées :

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://127.0.0.1:8787/'
app.appTimezone = 'Europe/Paris'

# Clé exigée par l'URL ?key=...  (videz-la pour désactiver la protection en local)
REPORT_API_KEY = 'demo-key'

# Fournisseur LLM (compatible API Mistral / OpenAI chat completions)
LLM_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions'
LLM_MODEL    = 'mistral-small-latest'
LLM_API_KEY  = '...'
```

- **`REPORT_API_KEY`** : protège la route. Si vide → accès libre (pratique en dev). Sinon, exigez `?key=...` (ou l'en-tête `X-Api-Key`).
- **`LLM_API_KEY`** : si présente, l'IA est active par défaut (désactivable via `&ai=0`). Si vide, l'analyse reste factuelle. (`MISTRAL_API_KEY` est aussi accepté comme alias.)
- **`app.baseURL`** : n'impacte que les liens internes ; l'accès passe par le port de `php spark serve`.

---

## 9. Dépannage

| Symptôme | Cause | Solution |
|---|---|---|
| « Site inaccessible » sur `127.0.0.1` | serveur en IPv6 uniquement | Utilisez **`http://localhost:8787`** (ou `--host 0.0.0.0`) |
| `401 Clé API invalide ou absente` | clé manquante/incorrecte | Ajoutez `?key=demo-key` à l'URL |
| `404 Bot inconnu` | dossier `writable/bots/<nom>/` absent | Créez le dossier du bot |
| `422 Conversations introuvables` | pas de `conversations.csv` | Ajoutez le fichier dans le dossier du bot |
| `Colonne "question" introuvable` | aucune colonne reconnue comme question | Renommez votre colonne (voir [§5](#étape-2--déposer-conversationscsv)) |
| La page `/` montre le logo CodeIgniter | normal, pas d'interface | Utilisez une URL `/rapport/<bot>?key=...` |
| `Address already in use` au démarrage | un serveur tourne déjà sur le port | `lsof -tiTCP:8787 -sTCP:LISTEN \| xargs kill -9` puis relancez |

---

## 10. Structure du projet

```
.
├── app/
│   ├── Config/
│   │   ├── Routes.php          # routes : '/' et 'rapport/(:segment)'
│   │   ├── Filters.php         # alias du filtre 'apikey'
│   │   └── Verifier.php        # réglages métier (étiquettes, seuils, LLM, extensions)
│   ├── Controllers/
│   │   └── ReportController.php # cœur : orchestre l'analyse et la génération
│   ├── Filters/
│   │   └── ApiKeyFilter.php     # protection par clé API
│   ├── Libraries/              # moteur d'analyse
│   │   ├── CsvLoader.php        # lecture + mapping souple des colonnes
│   │   ├── BaseLoader.php       # lecture de la base de connaissance
│   │   ├── Stats.php            # statistiques factuelles
│   │   ├── Classifier.php       # classification IA (intentions/sujets)
│   │   ├── QualityJudge.php     # jugement de qualité IA
│   │   ├── GroundingJudge.php   # jugement de fidélité à la base (IA)
│   │   ├── ImageChecker.php     # vérification d'existence des images
│   │   ├── LlmClient.php        # client LLM (Mistral/OpenAI compatible)
│   │   ├── ReportData.php       # agrégation des données du rapport
│   │   ├── AuditReport.php      # construction de l'audit + CSV
│   │   ├── PdfReport.php        # rendu PDF (mPDF)
│   │   └── RunTrace.php         # journal d'exécution / traçabilité
│   ├── Prompts/                # prompts IA (intent, quality, grounding)
│   └── Views/                  # gabarits PDF (report.php, audit.php, partials/)
├── public/                     # racine web (index.php)
├── writable/
│   ├── bots/                   # VOS bots (entrée)  ← demo fourni
│   └── reports/                # rapports générés (sortie, ignoré par git)
├── spark                       # CLI CodeIgniter
└── .env                        # configuration locale
```

---

### Mémo express

```bash
# 1. démarrer (depuis la racine)
php spark serve --port 8787

# 2. analyser, dans le navigateur
http://localhost:8787/rapport/demo?key=demo-key
```
