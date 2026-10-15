---
sidebar_position: 0
id: dossier-preuves-validation-0-2-0
title: Version 0.2.0
---

# Dossier de Preuves de Validation Fonctionnelle - Version 0.2.0

**Date :** 15 octobre 2026
**Version précédente :** `v0.1.0` (le tag `v0.1.0` n'existe pas dans le dépôt ; la plage retenue est `cd55fb3^..a9f7275`)
**Commit de rejeu :** `a9f72757b3336ccffc496d6e86aa405d87be389b` (`a9f7275`, branche `demo-start`)

## 1. Contexte

La version 0.2.0 apporte, depuis `v0.1.0`, trois corrections et deux évolutions de l'API JSON TicketFlow. Aucune n'est issue d'un document URS : chacune provient du journal applicatif (`var/log/dev.log`) ou d'une demande utilisateur du dossier `requests/`.

- **B1** - `GET /api/stats` renvoie des compteurs à zéro (HTTP 200) lorsqu'il n'existe aucun ticket, au lieu d'une erreur 500 (`DivisionByZeroError`). Demande humaine : `requests/003-dashboard-blank-on-monday.md`.
- **B2** - `POST /api/tickets` sans `title`, `description` ou `priority` (absent, `null` ou vide) renvoie HTTP 422 avec une table `errors` par champ, au lieu d'une erreur 500. Aucun ticket n'est enregistré. Origine : journal applicatif.
- **B3** - `GET /api/tickets/{id}` sur un identifiant inconnu renvoie HTTP 404 avec `{"error":"Ticket not found"}`, au lieu d'une erreur 500. Origine : journal applicatif.
- **F1** - Ajout de la priorité `urgent`, classée au-dessus de `high`. Toute autre priorité est refusée en 422 et `GET /api/stats` expose une ventilation `by_priority` (`low`, `normal`, `high`, `urgent`, `other`). Demande humaine : `requests/002-urgent-priority.md`.
- **F2** - `GET /api/tickets?status=<statut>` filtre la liste par statut. Un statut inconnu ou vide renvoie HTTP 400 avec `errors.status`. Demande humaine : `requests/001-filter-tickets-by-status.md`.

## 2. Avertissement

Ce dossier complète le rapport de validation officiel et ne le remplace pas. Des agents automatisés ont produit les spécifications, les tests et les vérifications. L'agent enregistreur de validation a rejoué lui-même les résultats qualifiés ici de « rejoués », au commit et à la date indiqués. Une revue de code n'équivaut pas à l'exécution d'un test. Dans ce dossier, aucune ligne ne repose sur une revue de code : chaque critère est couvert par un test rejoué.

## 3. Objectif de vérification

Vérifier que la version 0.2.0 :

**B1 - Statistiques sans ticket**
- B1-AC1 : renvoie 200 sur `GET /api/stats` avec une base vide, avec `total`, `open`, `in_progress` et `closed` à 0 ;
- B1-AC2 : renvoie, avec une base vide, un `closed_ratio` numérique égal à 0 ;
- B1-AC3 : renvoie `total` 2, `closed` 1, `open` 1 et `closed_ratio` 50 lorsqu'un ticket sur deux est clôturé ;
- B1-AC4 : ne casse pas le test existant `testStatsWithTickets`.

**B2 - Champs manquants à la création**
- B2-AC1 à AC3 : renvoie 422 avec `errors.priority`, `errors.title` ou `errors.description` lorsque le champ correspondant est absent ;
- B2-AC4 : renvoie, pour `{}`, 422 avec exactement les trois clés d'erreur ;
- B2-AC5 : renvoie 422 (et non 500) pour un corps de requête vide ;
- B2-AC6 : renvoie 422 pour un `title` vide, avec la seule clé `errors.title` ;
- B2-AC7 : renvoie 422 pour une `priority` à `null` ;
- B2-AC8 : n'enregistre aucun ticket après l'un de ces refus ;
- B2-AC9 : renvoie toujours 201, avec la même forme de réponse, pour un corps valide ;
- B2-AC10 : ne casse pas les tests existants `testCreateAndListTicket`, `testChangeStatus` et `testStatsWithTickets` ;
- B2-AC11 : conserve la réponse 422 `error`/`allowed` de `PATCH /api/tickets/{id}/status`.

**B3 - Ticket introuvable**
- B3-AC1 et AC2 : renvoie, pour `GET /api/tickets/9999`, 404 en JSON avec `{"error":"Ticket not found"}` ;
- B3-AC3 : renvoie 200 et la forme complète pour un ticket existant ;
- B3-AC4 : renvoie 404 pour un identifiant inconnu alors que d'autres tickets existent ;
- B3-AC5 : renvoie toujours 404 pour `PATCH /api/tickets/9999/status` ;
- B3-AC6 : ne casse pas les tests existants.

**F1 - Priorité urgente**
- F1-AC1 : définit `Ticket::PRIORITIES` à `['low','normal','high','urgent']` ;
- F1-AC2 : accepte `urgent` (201) et le restitue ;
- F1-AC3 et AC4 : refuse `critical` et `HIGH` en 422 avec `errors.priority`, sans enregistrement ;
- F1-AC5 : renvoie une erreur client (< 500), sans enregistrement, pour une priorité non textuelle (`5`) ;
- F1-AC6 : conserve le comportement de B2 pour une priorité absente ou vide ;
- F1-AC7 : accepte toujours `low`, `normal` et `high` ;
- F1-AC8 : renvoie, avec une base vide, `by_priority` à zéro sur les cinq clés ;
- F1-AC9 : compte correctement chaque priorité dans `by_priority` ;
- F1-AC10 : compte une priorité hors liste dans `other`, la somme des cinq clés étant égale à `total` ;
- F1-AC11 : restitue telle quelle une ligne existante de priorité hors liste ;
- F1-AC12 : ne modifie pas les clés de statistiques existantes.

**F2 - Filtre par statut**
- F2-AC1 et AC2 : ne renvoie, pour `?status=open|closed|in_progress`, que les tickets de ce statut ;
- F2-AC3 : renvoie tous les tickets sans paramètre ;
- F2-AC4 et AC5 : renvoie 200 avec une liste vide pour un statut connu sans correspondance ;
- F2-AC6 : renvoie 400 avec `errors.status` pour `?status=bogus` ; le message cite les valeurs autorisées et la réponse ne contient pas `items` ;
- F2-AC7 : renvoie 400 pour `?status=OPEN` (sensible à la casse) ;
- F2-AC8 : renvoie 400 pour `?status=` (valeur vide) ;
- F2-AC9 : ne renvoie jamais de liste pour un statut inconnu, même lorsque des tickets existent ;
- F2-AC10 et AC11 : conserve les réponses 422 de `POST /api/tickets` et de `PATCH /api/tickets/{id}/status` ;
- F2-AC12 : ne casse pas les tests existants.

### Périmètre non couvert

- **Interface utilisateur** : aucun tableau de bord ni navigateur n'a été exercé. Les demandes 001 et 003 évoquent un tableau de bord qui n'existe pas dans ce dépôt ; seule l'API JSON est vérifiée.
- **Déploiement réel** : aucun environnement de recette ou de production n'a été exercé. Toutes les exécutions ont eu lieu sur un poste Windows, avec SQLite (`var/data_dev.db` et une base de test par processus PHPUnit).
- **Exigences sans critère d'acceptation** : les verrous suivants ont été relevés par les vérificateurs mais ne sont pas testés :
  - B2, exigence 4 : absence de ligne de journal « Ticket created » lors d'un refus 422 ;
  - B3, exigence 4 : absence d'écriture en base pour un identifiant inconnu ;
  - F1, exigence 9 : restitution d'une ligne hors liste par `GET /api/tickets` (liste).
- **Exclusions explicites des spécifications** :
  - JSON malformé et valeurs de mauvais type sur `POST /api/tickets` (hors priorité numérique, couverte par F1-AC5) ;
  - `?status[]=...` sous forme de tableau sur `GET /api/tickets` ;
  - filtre et tri par priorité ;
  - pagination.
- **Contenu des réponses dans le rejeu de trafic** : le simulateur ne contrôle que les codes HTTP. Il ne vérifie pas que `GET /api/tickets?status=open` ne renvoie que des tickets ouverts.
- **Migration** : la plage ne contient aucune migration de base de données, donc aucune migration n'a été rejouée.

## 4. Méthodologie

- **Spécifications préalables** : pour chaque élément, une spécification (`specs/<id>-*.md`) fixe des critères d’acceptation numérotés avant toute modification du code. Les choix de contrat ont été soumis à une personne (voir section 7, « Décisions humaines »).
- **Tests écrits depuis la spécification** : un agent testeur, qui n’a pas vu le code applicatif, a écrit les tests `tests/Api/<id>*Test.php` à partir de la seule spécification, en parallèle du développement. Les critères de non-régression (B1-AC4, B2-AC10, B3-AC6, F2-AC12) s’appuient sur `tests/Api/TicketApiTest.php`, que la plage ne modifie pas (le diff de la plage `cd55fb3^..a9f7275` ne contient pas ce fichier).
- **Vérification indépendante** : chaque élément est passé par un tour de vérification, soit 5 tours au total. Chaque tour a obtenu PASS du premier coup (`verdict-1.json`) ; aucun tour de correction n’a été nécessaire. Chaque vérificateur a exécuté la suite complète, relu le diff et rejoué le trafic sur une base isolée.
- **Rejeu par l’enregistreur** : le 15 octobre 2026 (11:31, UTC+2), au commit `a9f7275`, l’enregistreur a exécuté :
  - `tools/php vendor/bin/phpunit --testdox` ;
  - `tools/php bin/console app:simulate-traffic --reset`, en recherchant `CRITICAL|ERROR|WARNING` dans les lignes ajoutées à `var/log/dev.log`.
- **Règle de preuve** : un test présent dans le dépôt n’est pas considéré comme une preuve de réussite. Seuls les résultats rejoués ci-dessus fondent la colonne « Conforme ». Les résultats des vérificateurs ont été obtenus sur des commits intermédiaires et ne servent ici qu’à la traçabilité.

## 5. Tests utilisateurs

Tous les tests nommés ci-dessous sont dans `tests/Api/` et ont été rejoués au commit `a9f7275` (suite : `OK (44 tests, 238 assertions)`).

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| B1-AC1 | Statistiques | `GET /api/stats`, base vide | 200, compteurs à 0 | `B1StatsZeroTicketsTest::testAc1EmptySchemaReturns200WithZeroCounts` : réussi (rejoué) | Oui |
| B1-AC2 | Statistiques | `GET /api/stats`, base vide | `closed_ratio` numérique = 0 | `B1StatsZeroTicketsTest::testAc2EmptySchemaClosedRatioIsNumericZero` : réussi (rejoué) | Oui |
| B1-AC3 | Statistiques | 2 tickets, 1 clôturé | `total` 2, `closed` 1, `open` 1, `closed_ratio` 50 | `B1StatsZeroTicketsTest::testAc3OneOfTwoClosedGivesFiftyPercent` : réussi (rejoué) | Oui |
| B1-AC4 | Non-régression | Test existant inchangé | Réussi | `TicketApiTest::testStatsWithTickets` : réussi (rejoué), fichier non modifié | Oui |
| B2-AC1 | Création de ticket | Sans `priority` | 422, `errors.priority` | `B2CreateTicketMissingFieldsTest::testAc1MissingPriorityReturns422` : réussi (rejoué) | Oui |
| B2-AC2 | Création de ticket | Sans `title` | 422, `errors.title` | `B2CreateTicketMissingFieldsTest::testAc2MissingTitleReturns422` : réussi (rejoué) | Oui |
| B2-AC3 | Création de ticket | Sans `description` | 422, `errors.description` | `B2CreateTicketMissingFieldsTest::testAc3MissingDescriptionReturns422` : réussi (rejoué) | Oui |
| B2-AC4 | Création de ticket | Corps `{}` | 422, trois clés d’erreur | `B2CreateTicketMissingFieldsTest::testAc4EmptyObjectReportsAllThreeFields` : réussi (rejoué) | Oui |
| B2-AC5 | Création de ticket | Corps vide | 422, trois clés d’erreur | `B2CreateTicketMissingFieldsTest::testAc5EmptyRequestBodyReturns422` : réussi (rejoué) | Oui |
| B2-AC6 | Création de ticket | `title` vide | 422, `errors.title` seul | `B2CreateTicketMissingFieldsTest::testAc6EmptyTitleOnlyReportsTitle` : réussi (rejoué) | Oui |
| B2-AC7 | Création de ticket | `priority` à `null` | 422, `errors.priority` | `B2CreateTicketMissingFieldsTest::testAc7NullPriorityReturns422` : réussi (rejoué) | Oui |
| B2-AC8 | Création de ticket | Après chaque refus | `count` 0 | `B2CreateTicketMissingFieldsTest::testAc8NothingPersistedAfterAnyFailure` : réussi (rejoué) | Oui |
| B2-AC9 | Non-régression | Corps valide | 201, `status` `open`, forme complète | `B2CreateTicketMissingFieldsTest::testAc9ValidBodyStillReturns201` : réussi (rejoué) | Oui |
| B2-AC10 | Non-régression | Tests existants inchangés | Réussis | `TicketApiTest::testCreateAndListTicket`, `testChangeStatus`, `testStatsWithTickets` : réussis (rejoués) | Oui |
| B2-AC11 | Non-régression | `PATCH .../status` avec `bogus` | 422, clés `error` et `allowed` | `B2CreateTicketMissingFieldsTest::testAc11PatchBogusStatusKeepsErrorShape` : réussi (rejoué) | Oui |
| B3-AC1 | Consultation de ticket | `GET /api/tickets/9999` | 404 | `B3ShowTicketNotFoundTest::testAc1UnknownIdReturns404` : réussi (rejoué) | Oui |
| B3-AC2 | Consultation de ticket | `GET /api/tickets/9999` | JSON `{"error":"Ticket not found"}` | `B3ShowTicketNotFoundTest::testAc2UnknownIdReturnsJsonErrorBody` : réussi (rejoué) | Oui |
| B3-AC3 | Consultation de ticket | Ticket existant | 200, forme complète | `B3ShowTicketNotFoundTest::testAc3ExistingTicketReturns200WithShape` : réussi (rejoué) | Oui |
| B3-AC4 | Consultation de ticket | `id` N+1000 avec données | 404 | `B3ShowTicketNotFoundTest::testAc4UnknownIdNextToExistingDataReturns404` : réussi (rejoué) | Oui |
| B3-AC5 | Non-régression | `PATCH /api/tickets/9999/status` | 404, même corps | `B3ShowTicketNotFoundTest::testAc5PatchUnknownIdStillReturns404` : réussi (rejoué) | Oui |
| B3-AC6 | Non-régression | Tests existants inchangés | Réussis | `TicketApiTest` (3 méthodes) : réussis (rejoués) | Oui |
| F1-AC1 | Priorités | Constante `Ticket::PRIORITIES` | `low`, `normal`, `high`, `urgent`, dans cet ordre | `F1UrgentPriorityTest::testAc1PrioritiesConstant` : réussi (rejoué) | Oui |
| F1-AC2 | Priorités | Création `urgent` | 201, restitué `urgent` | `F1UrgentPriorityTest::testAc2UrgentAccepted` : réussi (rejoué) | Oui |
| F1-AC3 | Priorités | `critical` | 422, `errors.priority`, `count` 0 | `F1UrgentPriorityTest::testAc3UnknownPriorityReturns422` : réussi (rejoué) | Oui |
| F1-AC4 | Priorités | `HIGH` | 422, rien enregistré | `F1UrgentPriorityTest::testAc4CaseSensitiveRejected` : réussi (rejoué) | Oui |
| F1-AC5 | Priorités | `priority` numérique `5` | < 500, JSON, `count` 0 | `F1UrgentPriorityTest::testAc5NonStringPriorityIsClientError` : réussi (rejoué) | Oui |
| F1-AC6 | Priorités | Absente puis vide | 422, `errors.priority` | `F1UrgentPriorityTest::testAc6MissingOrBlankPriorityReturns422` : réussi (rejoué) | Oui |
| F1-AC7 | Priorités | `low`, `normal`, `high` | 201 | `F1UrgentPriorityTest::testAc7ExistingPrioritiesStillAccepted` : réussi (rejoué) | Oui |
| F1-AC8 | Statistiques | Base vide | `by_priority` à zéro (5 clés) | `F1UrgentPriorityTest::testAc8EmptyStatsByPriority` : réussi (rejoué) | Oui |
| F1-AC9 | Statistiques | 5 tickets dont 2 `urgent` | `low` 1, `normal` 1, `high` 1, `urgent` 2, `other` 0 | `F1UrgentPriorityTest::testAc9StatsByPriority` : réussi (rejoué) | Oui |
| F1-AC10 | Statistiques | Ligne `critical` insérée en base | `other` 1, somme = `total` | `F1UrgentPriorityTest::testAc10OtherBucket` : réussi (rejoué) | Oui |
| F1-AC11 | Données existantes | Ligne `critical` | Restituée telle quelle | `F1UrgentPriorityTest::testAc11OutOfListRowReturnedUnchanged` : réussi (rejoué) | Oui |
| F1-AC12 | Non-régression | Clés de statistiques existantes | Valeurs inchangées | `F1UrgentPriorityTest::testAc12ExistingStatsKeysUnchanged` et `TicketApiTest::testStatsWithTickets` : réussis (rejoués) | Oui |
| F2-AC1 | Filtre par statut | `?status=open` | 200, `count` 1, tous `open` | `F2FilterTicketsByStatusTest::testAc1FilterOpenReturnsOnlyOpen` : réussi (rejoué) | Oui |
| F2-AC2 | Filtre par statut | `?status=closed`, `?status=in_progress` | 200, `count` 1 chacun | `F2FilterTicketsByStatusTest::testAc2FilterClosedAndInProgress` : réussi (rejoué) | Oui |
| F2-AC3 | Filtre par statut | Sans paramètre | 200, `count` 3 | `F2FilterTicketsByStatusTest::testAc3NoParameterReturnsAll` : réussi (rejoué) | Oui |
| F2-AC4 | Filtre par statut | Base vide, `?status=open` | 200, `items` vide, `count` 0 | `F2FilterTicketsByStatusTest::testAc4EmptySchemaKnownStatusReturnsEmptyList` : réussi (rejoué) | Oui |
| F2-AC5 | Filtre par statut | Seulement des `open`, `?status=closed` | 200, `items` vide, `count` 0 | `F2FilterTicketsByStatusTest::testAc5KnownStatusWithoutMatchReturnsEmptyList` : réussi (rejoué) | Oui |
| F2-AC6 | Filtre par statut | `?status=bogus` | 400, `errors.status` citant les 3 valeurs, sans `items` | `F2FilterTicketsByStatusTest::testAc6UnknownStatusReturns400` : réussi (rejoué) | Oui |
| F2-AC7 | Filtre par statut | `?status=OPEN` | 400, `errors.status` | `F2FilterTicketsByStatusTest::testAc7UppercaseStatusReturns400` : réussi (rejoué) | Oui |
| F2-AC8 | Filtre par statut | `?status=` (vide) | 400, `errors.status` | `F2FilterTicketsByStatusTest::testAc8EmptyStatusReturns400` : réussi (rejoué) | Oui |
| F2-AC9 | Filtre par statut | `?status=bogus` avec données | 400, pas de liste | `F2FilterTicketsByStatusTest::testAc9UnknownStatusWithTicketsIsNotAList` : réussi (rejoué) | Oui |
| F2-AC10 | Non-régression | `POST /api/tickets` avec corps vide `{}` | 422, 3 clés `errors` | `F2FilterTicketsByStatusTest::testAc10PostEmptyBodyStillReturns422` : réussi (rejoué) | Oui |
| F2-AC11 | Non-régression | `PATCH .../status` avec `bogus` | 422, `error` et `allowed` | `F2FilterTicketsByStatusTest::testAc11PatchBogusStatusStillReturns422` : réussi (rejoué) | Oui |
| F2-AC12 | Non-régression | Tests existants inchangés | Réussis | `TicketApiTest` (3 méthodes) : réussis (rejoués) | Oui |

**Bilan : 45 critères, 45 « Oui » obtenus par test rejoué, aucun « Oui (revue de code) », aucun « Non », aucun « Non validé par test ».**

## 6. Tests End-to-End

**Scénario** : `tools/php bin/console app:simulate-traffic --reset`, lancé le 15 octobre 2026 au commit `a9f7275` sur `var/data_dev.db`. Le simulateur vide la table des tickets, puis envoie 12 requêtes HTTP par le noyau Symfony :

| Requête | Statut |
|---------|--------|
| `GET /api/stats` (table vide) | 200 |
| `POST /api/tickets` (3 corps valides, dont un `urgent`) | 201, 201, 201 |
| `POST /api/tickets` (corps incomplet) | 422 |
| `GET /api/tickets` | 200 |
| `GET /api/tickets/1` | 200 |
| `GET /api/tickets/9999` | 404 |
| `PATCH /api/tickets/1/status` (`in_progress`) | 200 |
| `PATCH /api/tickets/1/status` (`done`) | 422 |
| `GET /api/tickets?status=open` | 200 |
| `GET /api/stats` | 200 |

**Comptes** : `HTTP 200: 6`, `HTTP 201: 3`, `HTTP 404: 1`, `HTTP 422: 2`. Aucune réponse 5xx.

**Journal** : `var/log/dev.log` est passé de 340 à 399 lignes. La recherche de `CRITICAL|ERROR|WARNING` dans les 59 lignes ajoutées ne renvoie rien. Les entrées `CRITICAL` des lignes 1 à 340 sont antérieures aux corrections B1, B2 et B3 et ne se reproduisent pas.

**Ce que ce rejeu prouve** :
- B1 : `GET /api/stats` sur une table vide répond 200, sans `DivisionByZeroError` ;
- B2 : un `POST` incomplet répond 422, et non 500 ;
- B3 : `GET /api/tickets/9999` répond 404, et non 500 ;
- F1 : la création `urgent` est acceptée (201) ;
- F2 : `GET /api/tickets?status=open` répond 200 sans erreur.

**Ce qu’il ne prouve pas** :
- le contenu des réponses, puisque le simulateur ne contrôle que les codes HTTP. En particulier, il ne vérifie pas que la réponse filtrée ne contient que des tickets `open`, ni la ventilation `by_priority` ;
- le refus d’une priorité inconnue (F1) ;
- la réponse 400 sur un statut inconnu (F2).

Ces points reposent uniquement sur les tests de la section 5.

## 7. Traçabilité / Logs

**Plage de commits** : `cd55fb3^..a9f7275`, branche `demo-start`. La vérification `rev-parse --verify v0.1.0` renvoie `fatal: Needed a single revision` : le tag n’existe pas.

| Élément | Commit de livraison | Commit documentation |
|---------|--------------------|----------------------|
| B1 | `11141a5` | `727d1cf` |
| B2 | `e9ab88a` | `e18b888` |
| B3 | `f036b8d` | `1e0fe19` |
| F1 | `3173073` | `e996316` |
| F2 | `4238f25` | `c6c9af1` |

**Commandes rejouées par l’enregistreur** (15 octobre 2026, 11:31 UTC+2, commit `a9f7275`, PHP 8.4.5, PHPUnit 13.4.2) :

```
tools/php vendor/bin/phpunit --testdox
  OK (44 tests, 238 assertions)        Time: 00:01.445, Memory: 40.00 MB

tools/php bin/console app:simulate-traffic --reset
  Schema reset: ticket table is empty.
  HTTP 200: 6
  HTTP 201: 3
  HTTP 404: 1
  HTTP 422: 2
  -> 0 ligne CRITICAL / ERROR / WARNING ajoutée à var/log/dev.log (lignes 341 à 399)
```

**Migrations** : aucun fichier n’est ajouté ou modifié sous `migrations/` dans la plage. La seule migration présente, `Version20261014130059.php`, est antérieure à la version. Aucun rejeu de migration n’est requis.

**Tours de vérification** :

| Élément | Tour | Verdict | Suite du vérificateur | CRITICAL hors périmètre signalés |
|---------|------|---------|-----------------------|----------------------------------|
| B1 | 1 | PASS | `OK (6 tests, 26 assertions)` | Deux, à `TicketController.php:40` et `:54` (corrigés ensuite par B2 et B3) |
| B2 | 1 | PASS | `OK (16 tests, 105 assertions)` | Un, à `TicketController.php:54` (corrigé ensuite par B3) |
| B3 | 1 | PASS | `OK (21 tests, 127 assertions)` | Aucun |
| F1 | 1 | PASS | `OK (33 tests, 174 assertions)` | Aucun |
| F2 | 1 | PASS | `OK (44 tests, 238 assertions)` | Aucun |

Aucun élément n’a connu de tour en échec ni de blocage.

**Approbations de périmètre** :
- `approvals/approved/improve-round-1.md` (B1, B2, B3, F1) : approuvé par Shen le 15 octobre 2026 (chat) ;
- `approvals/approved/improve-round-2.md` (F2) : approuvé par Shen le 15 octobre 2026 (chat).

Aucune demande n’est en attente dans `approvals/pending/`.

### Décisions humaines

| ID | Question | Options | Décision | Qui | Quand |
|----|----------|---------|----------|-----|-------|
| B2-D1 | `POST /api/tickets` sans `priority` : erreur de validation ou priorité par défaut ? | A. 422 `errors.priority` ; B. 400 `errors.priority` ; C. défaut `normal` (201) | A | Shen (chat) | 15 octobre 2026 |
| B2-D2 | Valider aussi `title` et `description` ? | A. `priority` seule ; B. les trois champs, 422 par champ ; C. `priority` seule, forme générique | B | Shen (chat) | 15 octobre 2026 |
| B2-D3 | Installer `symfony/property-access` et `symfony/property-info` pour `#[MapRequestPayload]` ? | A. installer, DTO `readonly` ; B. validation manuelle ; C. élément séparé préalable | A (reconfirmée après correction de la note de l’orchestrateur) | Shen (chat) | 15 octobre 2026 |
| F1-D1 | Lignes existantes de priorité hors liste ? | A. laisser, sans migration ; B. réécrire en `normal` ; C. supprimer ; D. compartiment `unknown` en lecture | A | Shen (chat) | 15 octobre 2026 |
| F1-D2 | Portée de « classée au-dessus » et « statistiques et filtres » ? | A. validation seule ; B. A + `by_priority` ; C. B + filtre `?priority=` ; D. C + tri | B | Shen (chat) | 15 octobre 2026 |
| F1-D3 | Comptage des lignes hors liste dans `by_priority` ? | A. les exclure ; B. cinquième clé `other` ; C. `other` seulement si > 0 | B | Shen (chat) | 15 octobre 2026 |
| F2-D1 | Forme de la réponse 400 pour un statut inconnu ? | A. `errors.status` ; B. `error` + `allowed` ; C. `errors.status` dont le message cite les valeurs autorisées | C | Shen (chat) | 15 octobre 2026 |
| F2-D2 | `?status=` vide : inconnu (400) ou ignoré ? (question posée par l’orchestrateur) | A. 400 comme un inconnu ; B. ignoré (200, tous les tickets) | A | Shen (chat) | 15 octobre 2026 |

Toutes les décisions portent la réponse d’une personne. Les fichiers `approvals/approved/decision-<item>-D<n>.md` et `reports/items/<item>/decisions.md` concordent. Aucune décision n’a été prise par un agent ou un orchestrateur seul. B1 et B3 n’ont requis aucune décision.

## 8. Anomalies détectées

| ID | Description | Gravité | Statut / Action |
|----|-------------|---------|-----------------|
| A1 | Le rejeu de trafic ne contrôle que les codes HTTP. `GET /api/tickets?status=open` répond 200, mais le contenu filtré, la ventilation `by_priority` et les refus 400 (F2) et 422 sur priorité inconnue (F1) ne sont prouvés que par les tests HTTP de la section 5, pas par le scénario de bout en bout. | Mineure | Ouverte. Étendre `app:simulate-traffic` avec une priorité inconnue et `?status=bogus`, et contrôler le contenu des réponses. |
| A2 | Inexactitude de preuve : la note du vérificateur F2 (`reports/items/F2/verdict-1.json`) affirme que le simulateur n’envoie jamais `?status`. Le rejeu de l’enregistreur montre qu’il envoie `GET /api/tickets?status=open` (route `ticket_list` journalisée dans `var/log/dev.log`). Le verdict reste valable, mais la note sous-estime la couverture du trafic. | Mineure | Ouverte. Pour information du relecteur ; aucune action sur le code. |
| A3 | Les tests de B1 et de B3 ont réussi dès leur première exécution par le testeur, en parallèle du correctif. Aucune preuve ne montre qu’ils échouent sur le code antérieur au correctif, donc leur capacité à détecter la régression n’est pas démontrée. Les tests de B2, F1 et F2 ont, eux, été observés en échec avant implémentation. | Mineure | Ouverte. Exécuter `B1StatsZeroTicketsTest` et `B3ShowTicketNotFoundTest` sur le `src/` du commit `cd55fb3` et consigner l’échec attendu. |
| A4 | B2, exigence 4 : l’absence de ligne de journal « Ticket created » lors d’un refus 422 n’est couverte par aucun test (signalé par le vérificateur B2). | Mineure | Ouverte. Ajouter un critère d’acceptation et un test, ou retirer l’exigence de la spécification. |
| A5 | B3, exigence 4 : l’absence d’écriture en base pour un identifiant inconnu n’est couverte par aucun test (signalé par le vérificateur B3). | Mineure | Ouverte. Même action que A4. |
| A6 | F1, exigence 9 : la restitution d’une ligne hors liste par `GET /api/tickets` (liste) n’est pas testée ; seul `GET /api/tickets/{id}` l’est (F1-AC11). De plus, F1-AC4 vérifie la présence de `errors.priority`, mais pas que le message est une chaîne non vide (signalé par le vérificateur F1). | Mineure | Ouverte. Compléter `F1UrgentPriorityTest`. |
| A7 | Incohérence d’enregistrement : dans `approvals/approved/decision-B2-D3.md`, la ligne « Decision » indique « already transitive », alors que la note corrigée de l’orchestrateur établit que les paquets n’étaient pas dans `composer.lock`. La reconfirmation de Shen après correction est consignée ; le fond de la décision (A) n’est pas en cause. | Mineure | Ouverte. Corriger la ligne « Decision » pour supprimer la mention erronée. |

**Total : 7 anomalies (0 bloquante, 0 majeure, 7 mineures).**

## 9. Conclusion

**La version 0.2.0 est validée au commit `a9f7275`.**

Les 45 critères d’acceptation des éléments B1, B2, B3, F1 et F2 sont couverts par des tests que l’enregistreur a lui-même rejoués avec succès au commit `a9f7275` (`OK (44 tests, 238 assertions)`). Le rejeu de trafic ne produit aucune réponse 5xx ni aucune ligne `CRITICAL`, `ERROR` ou `WARNING`. Les trois erreurs 500 d’origine (statistiques sur base vide, création sans priorité, ticket introuvable) ne se reproduisent plus. Chaque élément a obtenu PASS au premier tour de vérification, et toutes les décisions de contrat ont été prises par une personne.

Les anomalies relevées sont toutes mineures. Elles portent sur l’étendue des preuves (exigences sans critère d’acceptation, scénario de bout en bout limité aux codes HTTP, tests jamais observés en échec) et sur un défaut d’enregistrement. Aucune ne correspond à un comportement constaté en défaut.

Actions recommandées avant la validation officielle définitive :

1. Exécuter les tests de B1 et de B3 sur le code de `cd55fb3` et consigner leur échec (A3).
2. Ajouter les tests manquants pour B2 (exigence 4), B3 (exigence 4) et F1 (exigence 9, message de F1-AC4), ou aligner les spécifications (A4, A5, A6).
3. Étendre le simulateur de trafic aux cas de refus de F1 et F2 et au contrôle du contenu des réponses (A1).
4. Corriger la ligne « Decision » de `approvals/approved/decision-B2-D3.md` (A7).
5. Faire valider l’interface utilisateur et le déploiement réel, hors du périmètre de ce dossier, par le processus de validation officiel.

Ce dossier est une ébauche complémentaire au rapport de validation officiel ; il ne s’y substitue pas.
