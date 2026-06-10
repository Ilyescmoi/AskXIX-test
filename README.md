# Rapport d'analyse Chatbot (PHP + Bootstrap 5)

Petite app web qui génère **en un clic** un rapport **PDF** d'analyse à partir d'un **CSV de messages de chatbot**.

> Principe directeur : **tous les chiffres sont calculés en PHP, de façon déterministe.**
> L'IA (Mistral ou tout endpoint compatible) sert **uniquement** à attribuer des **étiquettes
> issues de listes fermées** (intention, sujet, qualité, hallucination). Elle ne produit jamais
> un nombre ni un texte réutilisé comme donnée.

## Fonctionnalités

- Upload d'un CSV (`date, user_id, question, reponse, [erreur]`) avec **mapping de colonnes
  souple** (noms proches et séparateur détectés automatiquement).
- **Stats factuelles** (PHP pur) : nb messages, utilisateurs uniques, messages/utilisateur,
  période couverte, répartition par heure, **taux de non-réponse par règles**.
- **Étiquetage IA en listes fermées** : intention + sujet de chaque question, qualité de chaque
  réponse + booléen d'hallucination. Traitement **par lots** avec **timeout + retry**.
- **Agrégation PHP** des étiquettes en pourcentages.
- **3 livrables par analyse**, reliés par une référence `#NNN` commune à chaque message :
  1. **Rapport PDF (1/2)** : page de garde, « En bref » avec sources, KPIs, activité par heure,
     typologies, top questions, questions sans réponse, meilleures réponses — chaque exemple
     cité porte sa référence, chaque section son badge « calculé » ou « estimé (IA) ».
  2. **Document de traçabilité PDF (2/2)** : chaîne de preuve complète — lecture du fichier
     source (SHA-256, mapping colonnes), formule + messages sources de chaque chiffre,
     décomposition des agrégats, détail des non-réponses, **journal du raisonnement IA**
     (prompts in extenso, appels lot par lot avec codes HTTP/durées/tentatives, corrections
     brut→retenu, lots en échec) et **registre complet de tous les messages** (paysage).
  3. **CSV de traçabilité** : 1 message = 1 ligne, textes intégraux, étiquettes retenues ET
     brutes, n° de lot IA, statut (`ok / corrige / absent_reponse / lot_echec / ia_desactivee`)
     — tout chiffre du rapport est recalculable depuis ce fichier.

## Anti-hallucination (garanties)

- L'IA répond en **JSON strict** (forcé via `response_format`), uniquement des **étiquettes**.
- Toute valeur **hors liste fermée, manquante ou douteuse** est ramenée à **`indetermine`**.
- Les **pourcentages** du rapport viennent **exclusivement** de comptages PHP.
- Prompt système restrictif : *« Tu classes uniquement d'après le texte fourni, aucune connaissance
  extérieure, en cas de doute "indetermine", JSON valide uniquement. »*

## Installation

Prérequis : PHP ≥ 8.1 (avec `mbstring`, `curl`), Composer.

```bash
composer install
```

## Clé API (jamais en dur)

La clé est lue **dans l'environnement**. Le plus simple : éditer le fichier `.env`
(à la racine, déjà présent, **gitignoré**), qui est chargé automatiquement par `config.php`
(via `env.php`, sans dépendance) :

```dotenv
# .env
MISTRAL_API_KEY=ta_cle
# MISTRAL_MODEL=mistral-small-latest                                  # optionnel
# MISTRAL_ENDPOINT=https://api.mistral.ai/v1/chat/completions         # optionnel (modèle local compatible)
```

Alternative sans fichier (l'environnement réel est **prioritaire** sur le `.env`) :

```bash
export MISTRAL_API_KEY="ta_cle"
```

> Sans clé API, l'app **fonctionne quand même** : elle produit le rapport **factuel** et marque
> les étiquettes comme `indetermine`.

## Lancer

```bash
php -S localhost:8000      # la clé du .env est lue automatiquement
```

Ouvre <http://localhost:8000>, charge un CSV (ou clique **« Utiliser le jeu d'exemple »**),
puis **« Générer le rapport »** → le PDF se télécharge.

## Structure

```
config.php            # clé API (getenv), modèle, taxonomies, règles non-réponse, section trace
env.php               # mini-chargeur de .env (sans dépendance)
index.php             # UI Bootstrap : upload + bouton un clic
generate.php          # contrôleur : CSV -> stats -> IA (tracée) -> agrégation -> 2 PDF + CSV
result.php            # page de téléchargement des 3 livrables
download.php          # téléchargement sécurisé (noms de fichiers stricts)
bootstrap.php         # autoload Composer + classes src/
src/CsvLoader.php     # lecture, mapping colonnes, validation (ids 1-based : #001 = 1re ligne)
src/Stats.php         # calculs factuels déterministes (ZÉRO IA), règle exacte par non-réponse
src/MistralClient.php # appel API cURL, batch, retry, timeout, JSON strict (tentatives tracées)
src/Classifier.php    # intentions + sujets (listes fermées), lots journalisés
src/QualityJudge.php  # qualité + hallucination (liste fermée), lots journalisés
src/LabelParsing.php  # trait commun : parse JSON + validation étiquette
src/RunTrace.php      # journal d'exécution : lots IA, tentatives HTTP, brut vs retenu, provenance
src/Text.php          # normalisation de texte + références #NNN (ref, refRanges)
src/ReportData.php    # fusion stats + agrégats en % — chaque ligne porte ses ids sources
src/AuditReport.php   # document de traçabilité (registre complet, journal IA) + CSV enrichi
src/PdfReport.php     # rendu PDF (mPDF, options par document)
templates/report.php  # gabarit HTML du rapport principal (Document 1/2)
templates/audit.php   # gabarit HTML du document de traçabilité (Document 2/2, §1–§9)
prompts/intent.txt    # prompt classification (éditable sans toucher au code)
prompts/quality.txt   # prompt jugement qualité (éditable sans toucher au code)
sample/messages.csv   # jeu d'exemple
```

## Traçabilité : comment vérifier un chiffre

1. Dans le **rapport (1/2)**, chaque exemple porte une référence `#NNN` et chaque section
   renvoie vers une section `§N` du **document de traçabilité (2/2)**.
2. Dans le document 2/2 : **§3** donne la formule et les références sources de chaque
   indicateur ; **§4–§6** décomposent chaque tableau ; **§7** journalise les appels IA
   (quels messages, dans quel lot, ce que le modèle a répondu, ce qui a été corrigé) ;
   **§9** liste TOUS les messages.
3. Le **CSV de traçabilité** contient les textes intégraux et tout ce qu'il faut pour
   recalculer chaque pourcentage (mêmes références `#NNN`).
4. Le fichier source est scellé par son **empreinte SHA-256**, affichée dans les deux PDF.

## Adapter à ton métier

- **Catégories** (intentions / sujets / qualité) : éditer les listes dans `config.php`.
- **Règles de non-réponse** (longueur min, formules de repli) : `config.php`.
- **Ton / consignes des prompts** : éditer `prompts/intent.txt` et `prompts/quality.txt`
  (les placeholders `{INTENTS}`, `{TOPICS}`, `{QUALITY_LABELS}`, `{ITEMS}` sont injectés par le code).
