---
sidebar_position: 0
id: dossier-preuves-validation-0-3-0
title: Version 0.3.0
---

# Dossier de Preuves de Validation Fonctionnelle - Version 0.3.0

Date : 14 octobre 2026. Version précédente : 0.2.0. Commit de référence : `05c7f08`
(branche `build/urs-002`, plage `46cb147..05c7f08`).

## 1. Contexte

La version 0.3.0 achève le module SLA décrit dans `docs/urs/URS-002-sla-module.md`
(cycle `/build`, sept items livrés séquentiellement sur la branche `build/urs-002`) et
intègre une demande humaine du cycle de maintenance. Depuis `v0.2.0` (socle F0), elle
apporte :

- **R1 - Filtre de la liste des tickets par statut** (`specs/R1-filter-tickets-by-status.md`,
  commit `5441fa6`, demande humaine `requests/001-filter-tickets-by-status.md`, contraintes
  URS-N-02 et URS-N-03). `GET /api/tickets?status=<open|in_progress|closed>` ne renvoie que
  les tickets du statut demandé, du plus récent au plus ancien ; une valeur inconnue ou non
  scalaire reçoit un 400 JSON `{"error": "Invalid status", "allowed": [...]}` ; sans
  paramètre, la liste est inchangée. `TicketRepository` expose un constructeur de requête
  composable (`createNewestFirstQueryBuilder()`, `withStatus()`, `findNewestFirst()`).
- **U1 - Création et liste des agents** (`specs/U1-agents-create-list.md`, commit `b78d25e`,
  URS-F-01.2, URS-F-01.3, contrainte URS-N-02). `POST /api/agents` (DTO
  `CreateAgentRequest` lié par `#[MapRequestPayload]`, 201, unicité exacte de l'email
  rendue en 422) et `GET /api/agents` (actifs d'abord, puis identifiant croissant,
  enveloppe `{"items": [...], "count": n}`). Un écouteur `ApiExceptionListener` rend en
  JSON les exceptions HTTP (400, 404, 405, 415, 422). L'item a ajouté les dépendances
  `symfony/property-access` et `symfony/property-info` (recette Flex), nécessaires au
  mapping du DTO (voir A4).
- **U3 - Affectation d'un ticket à un agent** (`specs/U3-ticket-assignee.md`, commit
  `a44175d`, URS-F-02.2, URS-F-02.3, URS-F-02.4, contraintes URS-N-01, URS-N-02,
  URS-N-03). `PATCH /api/tickets/{id}/assignee` avec `{"agentId": n}` affecte un agent
  actif (404 si inconnu, 422 si inactif), `{"agentId": null}` désaffecte ; la clé
  `assignee` de tout JSON ticket devient l'objet `{"id", "name"}` ou `null` ; chaque
  changement écrit une ligne `info` `Ticket assignee changed`.
- **U2 - Activation / désactivation d'un agent** (`specs/U2-agent-active-flag.md`, commit
  `87b6736`, URS-F-01.4, contraintes URS-N-01, URS-N-02). `PATCH /api/agents/{id}` avec
  `{"active": bool}` (DTO `UpdateAgentActiveRequest`), 200 avec l'agent, 422 si `active`
  manque ou n'est pas booléen, 404 si l'agent est inconnu ; ligne `info`
  `Agent activation changed` à chaque appel accepté ; la désactivation ne désaffecte pas
  les tickets.
- **U4 - Filtre des tickets dépassés** (`specs/U4-breached-filter.md`, commit `8fb7f22`,
  URS-F-04.1, URS-F-04.2, URS-F-04.3, contraintes URS-N-02, URS-N-03).
  `GET /api/tickets?breached=true` (resp. `false`) renvoie les tickets dont `dueAt` est
  strictement passé et dont le statut n'est pas `closed` (resp. le complément exact,
  `dueAt` `null` inclus) ; combinable avec `status`, l'erreur `status` étant prioritaire ;
  valeur inconnue en 400 JSON `{"error": "Invalid breached", "allowed": ["true", "false"]}`.
  `TicketRepository::withBreached()` porte l'unique prédicat de dépassement, à horloge
  épinglable.
- **U5 - Rapport SLA** (`specs/U5-sla-report.md`, commit `625b2ed`, URS-F-05.1,
  URS-F-05.2, URS-F-05.3, contrainte URS-N-02). `GET /api/reports/sla` renvoie
  `{"generatedAt", "priorities": [...]}` avec, pour `low`, `normal`, `high` (puis `other`
  si des priorités inconnues existent), `total`, `breached` et `breach_rate` (pourcentage
  arrondi à une décimale, toujours flottant), via le service `SlaReport` et
  `TicketRepository::countByPriority()`.
- **FX - Balayage d'acceptation URS-N** (`specs/FX-urs-n-acceptance-sweep.md`, commit
  `51f343d`, type *acceptance*, URS-N-01, URS-N-02, URS-N-03, section 6 de l'URS). Corrige
  les trois erreurs 500 antérieures (anomalies A1 à A3 du dossier 0.2.0) : `POST
  /api/tickets` est lié au DTO `CreateTicketRequest` (422 sur `title`, `description`,
  `priority` manquants ou invalides, `urgent` refusé), `GET /api/tickets/{id}` inconnu
  renvoie 404 JSON, `GET /api/stats` sur table vide renvoie 200 avec des zéros. Le
  scénario standard du simulateur ne produit plus aucun 5xx. La demande
  `requests/003-dashboard-blank-on-monday.md` est close par cet item ;
  `requests/002-urgent-priority.md` reste ouverte (voir A6).

Autres commits de la plage, sans item ni verdict de vérificateur : `136598d` (dossier
`approvals/`, script `.claude/hooks/human-gate.py`, liste de refus dans
`.claude/settings.json`), `b04fff4` (base SQLite par processus PHPUnit :
`tests/bootstrap.php`, `.env.test`, `CLAUDE.md` ; voir A2), les commits `trace:` et
`docs:` de chaque item (dossier `reports/items/`, pages `docs/user/`, fragments
`release/unreleased/`) et `05c7f08` (changelog 0.3.0). Aucun de ces commits ne touche
`src/`.

La plage ne contient **aucune migration** : `migrations/` reste limité à
`Version20261014130059` et `Version20261014133137` (0.2.0) ; aucun changement de schéma.

## 2. Avertissement

Ce dossier est **complémentaire** au rapport de validation officiel ; il ne s'y
substitue pas. Les preuves qu'il contient ont été produites par des agents automatisés
(rédacteur de spécification, développeur, testeur, vérificateur) puis **rejouées** par
l'agent enregistreur de validation sur le commit de référence, à la date indiquée.

Seuls les résultats marqués « rejoué » dans ce document correspondent à une exécution
réelle effectuée par l'enregistreur. Une mention « revue de code » signifie que
l'enregistreur a lu le code ou le diff correspondant, **sans** qu'un test l'exécute :
une revue de code n'est pas une exécution de test et ne constitue pas une preuve de
même niveau. La présence d'un test dans le dépôt n'est jamais considérée comme la preuve
qu'il passe.

## 3. Objectif de vérification

Vérifier que la version 0.3.0 :

**Item R1 - Filtre par statut** (URS-N-02, URS-N-03)

- AC1 : sans paramètre, `GET /api/tickets` renvoie tous les tickets du plus récent au plus
  ancien (`['closed', 'in_progress', 'open']` sur la fixture T-3h / T-2h / T-1h).
- AC2 : `?status=open` ne renvoie que les tickets `open`, le plus récent en premier.
- AC3 : chaque valeur de `Ticket::STATUSES` filtre correctement (`count === 1`).
- AC4 : un statut valide sans correspondance renvoie 200 `{"items": [], "count": 0}`.
- AC5 : `?status=done` renvoie 400 JSON `{"error": "Invalid status", "allowed": ["open", "in_progress", "closed"]}`.
- AC6 (URS-N-02) : `?status=`, `Open`, `OPEN`, `%20open`, `opne` renvoient le même 400.
- AC7 (URS-N-02) : `?status[]=open` et `?status[a]=open` renvoient 400 JSON, jamais HTML ni 500.
- AC8 (URS-N-03) : les clés de l'enveloppe et des items sont identiques avec et sans filtre.
- AC9 (URS-N-03) : `POST`, `GET /{id}`, `PATCH /{id}/status` (422 sur `done`) et `GET /api/stats` sont intacts.
- AC10 : `TicketRepository` expose `createNewestFirstQueryBuilder()`, `withStatus()` et `findNewestFirst()` composables.

**Item U1 - Agents : création et liste** (URS-F-01.2, URS-F-01.3, URS-N-02)

- AC1 (URS-F-01.3) : `POST /api/agents` `{"name", "email"}` renvoie 201 avec exactement `id, name, email, active` (`active === true`).
- AC2 (URS-F-01.3) : `GET /api/agents` liste l'agent créé, identique à la réponse du POST.
- AC3 (URS-F-01.3) : `"active": false` est stocké ; une clé inconnue est ignorée.
- AC4 (URS-N-02) : `{}` renvoie 422 avec `errors.name` et `errors.email` ; aucun agent créé.
- AC5 (URS-N-02) : `name` vide, blanc, `null`, numérique ou de 121 caractères renvoie 422 sur `errors.name` seul.
- AC6 (URS-N-02) : `email` vide, malformé, `null` ou de plus de 180 caractères renvoie 422 sur `errors.email` seul.
- AC7 (URS-N-02) : `"active": "yes"` renvoie 422 `errors.active` ; aucun agent créé.
- AC8 (URS-F-01.2) : un email déjà présent renvoie 422 `{"errors": {"email": "An agent with this email already exists."}}`.
- AC9 (URS-F-01.2) : le doublon est détecté via l'API ; la comparaison est exacte (`Dup@` accepté).
- AC10 (URS-N-02) : JSON malformé et corps vide renvoient 400 JSON.
- AC11 (URS-N-02) : `Content-Type: text/plain` renvoie 415 JSON.
- AC12 (URS-F-01.3) : la liste est triée actifs d'abord, puis identifiant croissant (`['Ann', 'Cal', 'Zed', 'Bea']`).
- AC13 (URS-F-01.3) : sans agent, `GET /api/agents` renvoie `{"items": [], "count": 0}`.
- AC14 (URS-F-01.3) : chaque item a exactement les clés et valeurs de `Agent::toArray()`.
- AC15 (URS-N-02) : `POST /api/tickets`, `PATCH /{id}/status` et `GET /api/stats` sont inchangés.
- AC16 : `AgentRepository::findActiveFirst()` renvoie l'ordre de l'AC12.

**Item U2 - Activation d'un agent** (URS-F-01.4, URS-N-01, URS-N-02)

- AC1 (URS-F-01.4) : `PATCH /api/agents/{id}` `{"active": false}` renvoie 200 avec l'agent désactivé.
- AC2 (URS-F-01.4) : `{"active": true}` réactive (200).
- AC3 (URS-F-01.4) : le changement est persisté et reflété dans l'ordre de `GET /api/agents`.
- AC4 (URS-F-01.4) : un no-op renvoie 200 ; `name`, `email`, `id` dans le corps sont ignorés.
- AC5 (URS-N-02) : `active` absent (`{}`, `{"other": 1}`) renvoie 422 sur `errors.active` seul.
- AC6 (URS-N-02) : `null`, `"yes"`, `"true"`, `""`, `1`, `0`, `[]` renvoient 422 ; l'agent reste actif.
- AC7 (URS-N-02) : identifiant inconnu renvoie 404 `{"error": "Agent not found"}`.
- AC8 (URS-N-02) : la validation précède la recherche (`/9999` avec `"yes"` renvoie 422).
- AC9 (URS-N-02) : identifiant non numérique renvoie 404 JSON.
- AC10 (URS-N-02) : `GET /api/agents/{id}` renvoie 405 JSON avec `Allow` contenant `PATCH`.
- AC11 (URS-N-02) : JSON malformé et corps vide renvoient 400 JSON.
- AC12 (URS-N-02) : `text/plain` renvoie 415 JSON.
- AC13 (URS-N-01) : la désactivation écrit exactement une ligne `info` `Agent activation changed` `{id, active: false}`.
- AC14 (URS-N-01) : l'activation est journalisée de même.
- AC15 (URS-N-01) : un no-op est journalisé avec la valeur inchangée.
- AC16 (URS-N-01) : les requêtes rejetées (404, 422, 415) ne sont pas journalisées.
- AC17 : `POST /api/agents` (201, doublon 422, `{}` 422), `POST /api/tickets`, `PATCH /{id}/status`, `GET /api/stats` sont inchangés.
- AC18 (URS-F-01.4) : la désactivation ne désaffecte pas les tickets de l'agent.

**Item U3 - Affectation d'un ticket** (URS-F-02.2, URS-F-02.3, URS-F-02.4, URS-N-01, URS-N-02, URS-N-03)

- AC1 (URS-F-02.2) : affecter un agent actif renvoie 200 avec `assignee` `{"id", "name"}` ; persisté.
- AC2 (URS-F-02.2) : une réaffectation remplace l'assigné ; l'opération est idempotente.
- AC3 (URS-F-02.2) : agent inconnu (`9999`, `0`) renvoie 404 `{"error": "Agent not found"}` et laisse le ticket inchangé.
- AC4 (URS-F-02.2) : agent inactif renvoie 422 `{"errors": {"agentId": "Agent is not active."}}` et laisse le ticket inchangé.
- AC5 (URS-F-02.2) : agent inactif sur un ticket non affecté renvoie 422, `assignee` reste `null`.
- AC6 (URS-N-02) : ticket inconnu renvoie 404 `{"error": "Ticket not found"}` (avec un `agentId` ou `null`).
- AC7 (URS-N-02) : identifiant de ticket non numérique renvoie 404 JSON.
- AC8 (URS-F-02.3) : `{"agentId": null}` désaffecte (200, `assignee` `null`, persisté).
- AC9 (URS-F-02.3) : la désaffectation est idempotente.
- AC10 (URS-N-02) : `agentId` absent renvoie 422 et ne désaffecte pas.
- AC11 (URS-N-02) : `agentId` mal typé (`"abc"`, `"5"`, `true`, `1.5`) renvoie 422.
- AC12 (URS-N-02) : JSON malformé et corps vide renvoient 400 JSON.
- AC13 (URS-N-02) : `text/plain` renvoie 415 JSON.
- AC14 (URS-F-02.4) : `GET /api/tickets` porte l'objet `assignee` ou `null` sur chaque item.
- AC15 (URS-F-02.4) : `GET /{id}` et `PATCH /{id}/status` portent l'objet `assignee`.
- AC16 (URS-F-02.4) : `POST /api/tickets` renvoie toujours `assignee` `null`.
- AC17 (URS-F-02.4) : un agent devenu inactif reste visible comme assigné.
- AC18 (URS-N-01) : l'affectation écrit une ligne `info` `Ticket assignee changed` `{id, assignee}`.
- AC19 (URS-N-01) : la désaffectation est journalisée avec `assignee: null`.
- AC20 (URS-N-01) : les requêtes rejetées (422, 404) ne sont pas journalisées.
- AC21 (URS-N-03) : `PATCH /{id}/status` (422), `?status=bogus` (400), `PATCH /9999/status` (404), `GET /api/stats` sont inchangés.

**Item U4 - Filtre des tickets dépassés** (URS-F-04.1, URS-F-04.2, URS-F-04.3, URS-N-02, URS-N-03)

- AC1 (URS-N-03) : sans paramètre, la liste est inchangée (plus récent en premier).
- AC2 (URS-F-04.1) : `?breached=true` ne renvoie que les tickets dépassés, non clos, `dueAt` non `null`.
- AC3 (URS-F-04.2) : `?breached=false` renvoie exactement le complément (union = liste complète, intersection vide).
- AC4 : `?breached=true` sans correspondance renvoie `{"items": [], "count": 0}`.
- AC5 (URS-F-04.2) : `?breached=yes` renvoie 400 `{"error": "Invalid breached", "allowed": ["true", "false"]}`.
- AC6 (URS-N-02) : `?breached=`, `1`, `0`, `True`, `TRUE`, `%20true`, `treu` renvoient le même 400.
- AC7 (URS-N-02) : `?breached[]=true` et `?breached[a]=true` renvoient 400 JSON.
- AC8 (URS-F-04.3) : `status` et `breached` se combinent dans les deux ordres, les deux prédicats s'appliquant.
- AC9 (URS-N-02) : valeurs invalides combinées : l'erreur `status` est prioritaire.
- AC10 (URS-N-03) : forme JSON inchangée ; `POST`, `GET /{id}`, `PATCH /{id}/status`, `GET /api/stats` intacts.
- AC11 : `TicketRepository::withBreached()` est l'unique prédicat de dépassement, à horloge épinglable, frontière stricte, empilable avec `withStatus()`, rétrocompatible.

**Item U5 - Rapport SLA** (URS-F-05.1, URS-F-05.2, URS-F-05.3, URS-N-02)

- AC1 (URS-F-05.3) : sur table vide, `GET /api/reports/sla` renvoie 200, `generatedAt` en `DATE_ATOM`, trois entrées à zéro avec `breach_rate` `0.0` flottant.
- AC2 (URS-F-05.1) : totaux, dépassés et taux par priorité (`50.0`, `33.3`, `100.0`).
- AC3 (URS-F-05.1) : taux arrondi à une décimale (`16.7`, `66.7`) ; sans dépassement `0.0`.
- AC4 (URS-F-05.2) : une priorité connue sans ticket est listée à zéro ; pas d'entrée `other` sans priorité inconnue.
- AC5 : la définition du dépassement est celle de U4 (`breached` et `total` égalent les comptes du filtre).
- AC6 : les priorités inconnues sont agrégées sous une entrée `other` en fin de liste.
- AC7 (URS-N-02) : toute query string est ignorée ; jamais 500 ni HTML.
- AC8 (URS-N-02) : `POST` et `DELETE` renvoient 405 JSON avec `Allow` contenant `GET`.
- AC9 (URS-N-03) : `GET /api/stats`, `GET /api/tickets`, `?breached=true`, `?breached=maybe`, `POST /api/tickets` sont intacts.
- AC10 : `SlaReport::generate($now)` et `TicketRepository::countByPriority()` comptent par le prédicat partagé à horloge épinglée.

**Item FX - Balayage d'acceptation URS-N** (URS-N-01, URS-N-02, URS-N-03, section 6)

- AC1 (URS-N-02) : `POST /api/tickets` sans `priority` renvoie 422 sur `errors.priority` seul.
- AC2 (URS-N-02) : `urgent`, `High`, `LOW`, `""`, `null`, `123` renvoient 422 ; `Ticket::PRIORITIES` reste `['low', 'normal', 'high']`.
- AC3 (URS-N-02) : `title` et `description` absents ou invalides renvoient 422 ciblé ; `{}` renvoie les trois clés.
- AC4 (URS-N-03) : une création valide est inchangée (201, huit clés, `dueAt - createdAt` = 8 h pour `high`, clé inconnue ignorée, titre de 120 caractères accepté).
- AC5 (URS-N-02) : JSON malformé et corps vide renvoient 400 ; `text/plain` renvoie 415.
- AC6 (URS-N-02) : `GET /api/tickets/9999` et `/0` renvoient 404 `{"error": "Ticket not found"}`.
- AC7 (URS-N-02) : `GET /api/tickets/abc` renvoie 404 JSON ; `DELETE /api/tickets/1` renvoie 405 JSON avec `Allow` contenant `GET`.
- AC8 (URS-N-02) : `GET /api/stats` sur table vide renvoie 200 avec des zéros.
- AC9 (URS-N-03) : `GET /api/stats` avec tickets est inchangé (`closed_ratio` 33.3).
- AC10 (section 6) : les douze requêtes du scénario standard, rejouées par le client de test, donnent exactement `[200, 201, 201, 422, 422, 200, 200, 404, 200, 422, 200, 200]`, toutes en JSON.
- AC11 (section 6) : `app:simulate-traffic --reset` sort en code 0, sans 5xx, avec `HTTP 422: 3` et `HTTP 404: 1`.
- AC12 (URS-N-01) : affectation, désaffectation et désactivation écrivent chacune exactement une ligne `info`.
- AC13 (URS-F-03.2, URS-N-03) : seul `F0SlaFoundationTest::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget` est amendé (`urgent` en 422) ; les assertions entité sont conservées.
- AC14 (URS-N-03) : `tests/Api/TicketApiTest.php` est inchangé ; les quatre points d'entrée ticket gardent leurs huit clés et `PATCH /{id}/status` `done` reste 422.

### Périmètre non couvert

- **Aucun parcours navigateur** : TicketFlow est une API JSON ; aucune interface graphique
  n'est exercée.
- **Aucun déploiement réel ni service externe** : toutes les preuves sont produites en
  local sur SQLite (`var/data_dev.db` pour le trafic, `var/data_test_<pid>.db` pour la
  suite).
- **Trafic simulé limité aux points d'entrée de 0.1.0** : le scénario standard de
  `app:simulate-traffic` n'appelle ni `/api/agents`, ni `PATCH /api/tickets/{id}/assignee`,
  ni `?breached=`, ni `/api/reports/sla`. Les items U1 à U5 ne sont donc prouvés que par
  la suite PHPUnit, pas par le rejeu de trafic (voir section 6 et A7).
- **Contenu JSON sous trafic simulé** : le simulateur ne vérifie que les codes HTTP ; le
  contenu des réponses (clé `assignee`, corps d'erreur) n'est prouvé que par PHPUnit.
- **Journal d'audit (URS-N-01) uniquement en environnement de test** : les lignes
  `Ticket assignee changed` et `Agent activation changed` sont prouvées par un
  `TestHandler` Monolog (U2 AC13 à AC16, U3 AC18 à AC20, FX AC12) ; leur présence dans
  `var/log/dev.log` sous trafic réel n'est pas exercée, le simulateur n'appelant pas ces
  points d'entrée.
- **`ON DELETE SET NULL`** : aucun item n'autorise la suppression d'un agent ; la clause
  (schéma 0.2.0) n'est toujours pas exercée.
- **Priorité `urgent`** : la demande `requests/002-urgent-priority.md` n'est pas traitée ;
  `urgent` est désormais refusé en 422 (FX AC2), au lieu d'être accepté avec une cible de
  24 h comme en 0.2.0 (voir A6).
- **Hors spécification, délibérément** : pas de `GET /api/agents/{id}`, pas de suppression
  d'agent, pas de pagination, pas de filtre par priorité ou par assigné, pas de paramètre
  `now` sur le rapport, pas de validation de `PATCH /api/tickets/{id}/status` au-delà de
  l'existant (il conserve son `json_decode()`).
- **Commit d'infrastructure `b04fff4`** (base SQLite par processus) : pas d'item, pas de
  verdict ; seul son effet (suite stable en un seul processus) est observé par le rejeu.

## 4. Méthodologie

1. **Spécification avant code.** Chaque item dispose d'une spécification dans `specs/`
   rédigée par l'agent spec-writer à partir de l'URS-002 (U1 à U5, FX) ou de la demande
   humaine (R1), avec des critères d'acceptation numérotés et leurs identifiants URS,
   avant toute ligne de code : R1 10 critères, U1 16, U2 18, U3 21, U4 11, U5 10, FX 14,
   soit **100 critères**.
2. **Tests écrits depuis la spécification.** L'agent testeur a écrit, pour chaque item, une
   classe `tests/Api/<Item>...Test.php` héritée de `ApiTestCase` à partir de la seule
   spécification, sans accès au code produit par l'agent développeur, qui travaillait en
   parallèle. Chaque critère correspond à une méthode de test nommée `testAc<n>...` et
   portant l'identifiant URS (`UrsN02`, `UrsF0202`, ...). Pour FX, l'unique modification
   d'un test existant est l'amendement AC13 de `F0SlaFoundationTest`.
3. **Vérification indépendante.** L'agent vérificateur a confronté le code et les tests à
   la spécification. Chaque item a été validé en **un seul tour** (`verdict-1.json`,
   `PASS`, détail en section 7) ; aucun tour `FAIL` n'a été nécessaire. Les commits d'item
   n'ont été créés qu'après le `PASS`.
4. **Rejeu par l'enregistreur.** Le présent dossier repose sur un rejeu effectué le
   14 octobre 2026 sur le commit `05c7f08` (HEAD de `build/urs-002`) :
   - `tools/php vendor/bin/phpunit` (suite complète, un seul processus, base
     `var/data_test_<pid>.db` dédiée) ;
   - `tools/php bin/console app:simulate-traffic --reset` (codes HTTP et lignes ajoutées à
     `var/log/dev.log`) ;
   - pas de rejeu de migration : la plage ne contient aucune migration
     (`git diff --name-only 46cb147..HEAD -- migrations` vide).
   Au moment du rejeu, `src/`, `tests/`, `migrations/`, `config/`, `composer.json` et
   `composer.lock` étaient identiques au commit ; seuls deux fichiers de
   `docs/user/annexe/flash-info/` étaient modifiés ou non suivis (rédaction concurrente du
   flash-info 0.3.0), sans effet sur l'exécution.
5. **Lecture des preuves uniquement.** L'enregistreur a lu les verdicts
   (`reports/items/<id>/verdict-1.json`), les rapports du testeur (`tester.md`), les
   `item.json`, les spécifications et les fichiers de test. Il n'a lu ni les rapports du
   développeur, ni les fragments de changelog, ni les messages de commit, ni `src/`.
   Lorsqu'une clause n'est couverte par aucun test (FX AC13, clause « aucune autre ligne
   ne change » ; FX AC14, clause « `TicketApiTest` octet pour octet identique » ; clauses
   « les tests existants restent verts **inchangés** » de chaque spécification), elle est
   marquée « revue de code » et porte sur la lecture effective de
   `git diff 46cb147..HEAD -- tests/` : seuls `tests/Api/F0SlaFoundationTest.php`
   (`+3 -2`, lignes 210 à 214, amendement FX AC13) et `tests/bootstrap.php` (`+15`,
   commit `b04fff4`) changent parmi les fichiers de test préexistants ;
   `tests/Api/TicketApiTest.php` et `tests/Api/ApiTestCase.php` ont un diff vide.

L'existence d'un test dans le dépôt n'est pas traitée comme une preuve de passage ; seul
le résultat du rejeu de l'étape 4 fait foi.

## 5. Tests utilisateurs

Statut rejoué le 14 octobre 2026 sur `05c7f08` : `OK (117 tests, 1128 assertions)`.
Toutes les méthodes citées ci-dessous figurent dans la sortie `--testdox` du rejeu avec
le statut « passe » ; aucune n'est en échec, ignorée, incomplète ou marquée risquée.

**Item R1** (`tests/Api/R1FilterTicketsByStatusTest.php`, 10 tests)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| R1-AC1 | Liste sans filtre | Trois tickets `open` (T-3h), `in_progress` (T-2h), `closed` (T-1h) ; `GET /api/tickets` | 200, `count === 3`, statuts `['closed', 'in_progress', 'open']` | `testAc1WithoutParameterReturnsAllTicketsNewestFirst` : rejoué, passe | Oui |
| R1-AC2 | Filtre `open` | Quatre tickets dont deux `open` ; `GET /api/tickets?status=open` | 200, `count === 2`, `items[0].title === 'Newest open'`, tous `open` | `testAc2StatusOpenReturnsOnlyOpenTicketsNewestFirst` : rejoué, passe | Oui |
| R1-AC3 | Chaque statut | Un ticket par statut ; `?status=<v>` pour chaque `v` | 200, `count === 1`, `items[0].status === v` | `testAc3EveryKnownStatusFiltersCorrectly` : rejoué, passe | Oui |
| R1-AC4 | Statut valide sans résultat | Un `open` ; `?status=closed` | 200, corps exactement `{"items": [], "count": 0}` | `testAc4ValidStatusWithNoMatchReturnsEmptyList` : rejoué, passe | Oui |
| R1-AC5 | Statut inconnu | `?status=done` | 400 JSON, corps exactement `{"error": "Invalid status", "allowed": ["open", "in_progress", "closed"]}` | `testAc5UnknownStatusReturns400NamingAllowedValues` : rejoué, passe | Oui |
| R1-AC6 (URS-N-02) | Valeurs limites | `?status=`, `Open`, `OPEN`, `%20open`, `opne` | 400 JSON à chaque fois, `error` et `allowed` comme AC5 | `testAc6UrsN02EdgeValuesAreRejectedWith400Never500` : rejoué, passe | Oui |
| R1-AC7 (URS-N-02) | Valeur non scalaire | `?status[]=open`, `?status[a]=open` | 400, `Content-Type` JSON, corps d'AC5 décodable | `testAc7UrsN02NonScalarValueIsJson400NotHtml` : rejoué, passe | Oui |
| R1-AC8 (URS-N-03) | Forme JSON | `GET /api/tickets` et `?status=open` sur un ticket | Clés `items, count` et huit clés ticket ; `items[0]` identique | `testAc8UrsN03JsonShapeUnchangedWithAndWithoutFilter` : rejoué, passe | Oui |
| R1-AC9 (URS-N-03) | Autres points d'entrée | `POST`, `GET /{id}`, `PATCH /{id}/status` `done`, `GET /api/stats` | 201, 200, 422 `Invalid status`, cinq clés stats | `testAc9UrsN03OtherTicketEndpointsAreUntouched` : rejoué, passe | Oui |
| R1-AC10 | Repository composable | `createNewestFirstQueryBuilder()`, `withStatus()`, `andWhere` empilé, `findNewestFirst()` | `QueryBuilder`, DQL contenant `t.status = :status`, deux résultats `open`/`high`, même liste que `findAllNewestFirst()` | `testAc10RepositoryExposesComposableQueryBuilderPieces` : rejoué, passe | Oui |

**Item U1** (`tests/Api/U1AgentsCreateListTest.php`, 16 tests)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| U1-AC1 (URS-F-01.3) | Création d'agent | `POST /api/agents` `{"name": "Ada", "email": "ada@example.test"}` | 201 JSON, clés exactement `id, name, email, active`, `active === true` | `testAc1UrsF0103CreateAgentReturns201WithAgentShape` : rejoué, passe | Oui |
| U1-AC2 (URS-F-01.3) | Liste après création | `GET /api/agents` | 200, `count === 1`, `items[0]` identique au corps du POST | `testAc2UrsF0103CreatedAgentIsListed` : rejoué, passe | Oui |
| U1-AC3 (URS-F-01.3) | `active: false` et clé inconnue | POST avec `"active": false` ; POST avec `"colour": "blue"` | 201 `active === false` ; 201 sans clé `colour` | `testAc3UrsF0103ActiveFalseStoredAndUnknownKeysIgnored` : rejoué, passe | Oui |
| U1-AC4 (URS-N-02) | Champs manquants | `POST /api/agents` `{}` | 422 JSON, clé `errors` avec `name` et `email` non vides ; liste `count === 0` | `testAc4UrsN02MissingFieldsReturn422WithBothErrorKeys` : rejoué, passe | Oui |
| U1-AC5 (URS-N-02) | `name` invalide | `""`, `"   "`, `null`, `123`, 121 `x` | 422 JSON, `errors.name` non vide, pas d'`errors.email` | `testAc5UrsN02InvalidNameReturns422` : rejoué, passe | Oui |
| U1-AC6 (URS-N-02) | `email` invalide | `""`, `not-an-email`, `ada@`, `null`, 181+ caractères | 422 JSON, `errors.email` non vide, pas d'`errors.name` | `testAc6UrsN02InvalidEmailReturns422` : rejoué, passe | Oui |
| U1-AC7 (URS-N-02) | `active` non booléen | `"active": "yes"` | 422 `errors.active` ; liste `count === 0` | `testAc7UrsN02NonBooleanActiveReturns422` : rejoué, passe | Oui |
| U1-AC8 (URS-F-01.2) | Doublon d'email | `createAgent('Ada', ...)` puis POST même email | 422, corps exactement `{"errors": {"email": "An agent with this email already exists."}}` ; liste `count === 1` | `testAc8UrsF0102DuplicateEmailReturns422` : rejoué, passe | Oui |
| U1-AC9 (URS-F-01.2) | Doublon via l'API, casse | POST `dup@`, POST identique, POST `Dup@` | 201, 422, 201 ; `count === 2` | `testAc9UrsF0102DuplicateEmailViaApiIsCaseSensitive` : rejoué, passe | Oui |
| U1-AC10 (URS-N-02) | JSON malformé | `{not json` et corps vide, `application/json` | 400 JSON, `error` non vide | `testAc10UrsN02MalformedJsonReturnsJson400` : rejoué, passe | Oui |
| U1-AC11 (URS-N-02) | Type de contenu | `text/plain` | 415 JSON, `error` non vide | `testAc11UrsN02UnsupportedContentTypeReturnsJson415` : rejoué, passe | Oui |
| U1-AC12 (URS-F-01.3) | Tri de la liste | Zed (inactif), Ann, Bea (inactive), Cal | Noms `['Ann', 'Cal', 'Zed', 'Bea']`, `active` `[true, true, false, false]` | `testAc12UrsF0103ListIsActiveFirstThenIdAscending` : rejoué, passe | Oui |
| U1-AC13 (URS-F-01.3) | Liste vide | `GET /api/agents` sans agent | 200, corps exactement `{"items": [], "count": 0}` | `testAc13UrsF0103EmptyListReturnsEmptyEnvelope` : rejoué, passe | Oui |
| U1-AC14 (URS-F-01.3) | Forme d'un item | Un agent ; `GET /api/agents` | `items[0]` avec clés et valeurs de `$agent->toArray()` | `testAc14UrsF0103ListItemHasAgentShape` : rejoué, passe | Oui |
| U1-AC15 (URS-N-02) | Points d'entrée existants | `POST /api/tickets`, `PATCH /{id}/status` `done`, `GET /api/stats` | 201 huit clés, 422 `Invalid status`, cinq clés | `testAc15UrsN02ExistingEndpointsUnchanged` : rejoué, passe | Oui |
| U1-AC16 | `findActiveFirst()` | Quatre agents d'AC12, appel au repository | Séquence `['Ann', 'Cal', 'Zed', 'Bea']` | `testAc16RepositoryFindActiveFirstOrdering` : rejoué, passe | Oui |

**Item U2** (`tests/Api/U2AgentActiveFlagTest.php`, 18 tests)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| U2-AC1 (URS-F-01.4) | Désactivation | `PATCH /api/agents/{id}` `{"active": false}` | 200 JSON, corps exactement `{id, "Ada", "ada@example.test", false}` | `testAc1UrsF0104DeactivateReturns200WithAgentShape` : rejoué, passe | Oui |
| U2-AC2 (URS-F-01.4) | Réactivation | Agent inactif ; `{"active": true}` | 200, `active === true`, `name`, `email` conservés | `testAc2UrsF0104ReactivateReturns200` : rejoué, passe | Oui |
| U2-AC3 (URS-F-01.4) | Persistance et ordre | Ann, Bea actives ; PATCH Ann `false` ; `GET /api/agents` | `count === 2`, noms `['Bea', 'Ann']`, `active` `[true, false]` | `testAc3UrsF0104ChangeIsPersistedAndReflectedInListOrder` : rejoué, passe | Oui |
| U2-AC4 (URS-F-01.4) | No-op, clés ignorées | `{"active": true, "name": "Mallory", "email": ..., "id": 9999}` | 200, corps inchangé (`Ada`, `ada@example.test`) ; liste `count === 1` | `testAc4UrsF0104NoOpReturns200AndExtraKeysAreIgnored` : rejoué, passe | Oui |
| U2-AC5 (URS-N-02) | `active` manquant | `{}` brut et `{"other": 1}` | 422 JSON, `errors` avec exactement `active` ; agent toujours actif | `testAc5UrsN02MissingActiveReturns422` : rejoué, passe | Oui |
| U2-AC6 (URS-N-02) | `active` non booléen | `null`, `"yes"`, `"true"`, `""`, `1`, `0`, `[]` | 422 JSON à chaque fois ; agent toujours actif | `testAc6UrsN02NonBooleanActiveReturns422` : rejoué, passe | Oui |
| U2-AC7 (URS-N-02) | Identifiant inconnu | `PATCH /api/agents/9999` `{"active": false}` | 404 JSON, corps exactement `{"error": "Agent not found"}` | `testAc7UrsN02UnknownIdReturns404` : rejoué, passe | Oui |
| U2-AC8 (URS-N-02) | Validation avant recherche | `/9999` avec `{"active": "yes"}` | 422 `errors.active` (et non 404) | `testAc8UrsN02ValidationPrecedesLookup` : rejoué, passe | Oui |
| U2-AC9 (URS-N-02) | Identifiant non numérique | `PATCH /api/agents/abc` | 404 JSON, `error` non vide | `testAc9UrsN02NonNumericIdReturnsJson404` : rejoué, passe | Oui |
| U2-AC10 (URS-N-02) | Mauvaise méthode | `GET /api/agents/{id}` | 405 JSON, `error` non vide, `Allow` contient `PATCH` | `testAc10UrsN02WrongMethodReturnsJson405WithAllowHeader` : rejoué, passe | Oui |
| U2-AC11 (URS-N-02) | JSON malformé / vide | `{not json`, corps vide | 400 JSON ; agent toujours actif | `testAc11UrsN02MalformedAndEmptyBodyReturnJson400` : rejoué, passe | Oui |
| U2-AC12 (URS-N-02) | Type de contenu | `text/plain` | 415 JSON ; agent toujours actif | `testAc12UrsN02UnsupportedContentTypeReturnsJson415` : rejoué, passe | Oui |
| U2-AC13 (URS-N-01) | Journal : désactivation | `TestHandler` Monolog ; PATCH `false` | Exactement un enregistrement `info` `Agent activation changed`, contexte `{id, active: false}` | `testAc13UrsN01DeactivationIsLoggedAtInfo` : rejoué, passe | Oui |
| U2-AC14 (URS-N-01) | Journal : activation | Agent inactif ; PATCH `true` | Enregistrement avec contexte `{id, active: true}` | `testAc14UrsN01ActivationIsLoggedAtInfo` : rejoué, passe | Oui |
| U2-AC15 (URS-N-01) | Journal : no-op | Agent actif ; PATCH `true` | Enregistrement avec contexte `{id, active: true}` | `testAc15UrsN01NoOpIsLoggedWithUnchangedValue` : rejoué, passe | Oui |
| U2-AC16 (URS-N-01) | Journal : rejets | 404, 422, 415 avec `disableReboot()` | Aucun enregistrement `Agent activation changed` | `testAc16UrsN01RejectedRequestsAreNotLogged` : rejoué, passe | Oui |
| U2-AC17 | Points d'entrée existants | `POST /api/agents` x3, `POST /api/tickets`, `PATCH /{id}/status` `done`, `GET /api/stats` | 201, 422 doublon, 422 `{}` ; 201 huit clés ; 422 ; cinq clés | `testAc17ExistingEndpointsUnchanged` : rejoué, passe | Oui |
| U2-AC18 (URS-F-01.4) | Pas de désaffectation | Ticket affecté ; PATCH agent `false` ; rechargement | `getAssignee()->getId()` inchangé, `isActive() === false` | `testAc18UrsF0104DeactivationDoesNotUnassignTickets` : rejoué, passe | Oui |

**Item U3** (`tests/Api/U3TicketAssigneeTest.php`, 21 tests)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| U3-AC1 (URS-F-02.2) | Affectation | `PATCH /api/tickets/{id}/assignee` `{"agentId": n}` | 200 JSON, huit clés, `assignee` exactement `{"id": n, "name": "Ada"}` ; persisté après `clear()` | `testAc1UrsF0202AssignActiveAgentReturns200WithAssigneeObject` : rejoué, passe | Oui |
| U3-AC2 (URS-F-02.2) | Réaffectation | Ticket affecté à Ada ; PATCH Bob ; PATCH Bob à nouveau | 200 `assignee` Bob, `GET /{id}` idem ; second PATCH 200 identique | `testAc2UrsF0202ReassignReplacesAssigneeAndIsIdempotent` : rejoué, passe | Oui |
| U3-AC3 (URS-F-02.2) | Agent inconnu | `{"agentId": 9999}` puis `{"agentId": 0}` | 404 JSON `{"error": "Agent not found"}` ; assigné inchangé | `testAc3UrsF0202UnknownAgentReturnsJson404AndLeavesTicketUnchanged` : rejoué, passe | Oui |
| U3-AC4 (URS-F-02.2) | Agent inactif | Ticket affecté à Ada ; PATCH Zed (inactif) | 422 JSON exactement `{"errors": {"agentId": "Agent is not active."}}` ; assigné Ada conservé (API et base) | `testAc4UrsF0202InactiveAgentReturns422AndLeavesTicketUnchanged` : rejoué, passe | Oui |
| U3-AC5 (URS-F-02.2) | Agent inactif, ticket libre | PATCH Zed sur ticket non affecté | 422 corps d'AC4 ; `assignee === null` | `testAc5UrsF0202InactiveAgentOnUnassignedTicketReturns422` : rejoué, passe | Oui |
| U3-AC6 (URS-N-02) | Ticket inconnu | `/api/tickets/9999/assignee` avec `agentId` puis `null` | 404 JSON exactement `{"error": "Ticket not found"}` | `testAc6UrsN02UnknownTicketReturnsJson404` : rejoué, passe | Oui |
| U3-AC7 (URS-N-02) | Identifiant non numérique | `/api/tickets/abc/assignee` | 404 JSON, `error` non vide | `testAc7UrsN02NonNumericTicketIdReturnsJson404` : rejoué, passe | Oui |
| U3-AC8 (URS-F-02.3) | Désaffectation | Ticket affecté ; `{"agentId": null}` | 200, huit clés, `assignee === null` ; `GET /{id}` `null` ; base `null` | `testAc8UrsF0203UnassignReturns200WithNullAssignee` : rejoué, passe | Oui |
| U3-AC9 (URS-F-02.3) | Désaffectation idempotente | Ticket libre ; `{"agentId": null}` | 200 `assignee === null` | `testAc9UrsF0203UnassignIsIdempotent` : rejoué, passe | Oui |
| U3-AC10 (URS-N-02) | `agentId` manquant | `{}` brut et `{"other": 1}` | 422 JSON, `errors.agentId` non vide ; assigné inchangé | `testAc10UrsN02MissingAgentIdReturns422NotUnassignment` : rejoué, passe | Oui |
| U3-AC11 (URS-N-02) | `agentId` mal typé | `"abc"`, `"5"`, `true`, `1.5` | 422 JSON `errors.agentId` à chaque fois | `testAc11UrsN02MistypedAgentIdReturns422` : rejoué, passe (sans dépréciation au rejeu ; voir A5) | Oui |
| U3-AC12 (URS-N-02) | JSON malformé / vide | `{not json`, corps vide | 400 JSON, `error` non vide | `testAc12UrsN02MalformedJsonReturnsJson400` : rejoué, passe | Oui |
| U3-AC13 (URS-N-02) | Type de contenu | `text/plain` | 415 JSON, `error` non vide | `testAc13UrsN02UnsupportedContentTypeReturnsJson415` : rejoué, passe | Oui |
| U3-AC14 (URS-F-02.4) | Liste | `Assigned` (T-2h, Ada), `Free` (T-1h) ; `GET /api/tickets` | `items[0]` `Free` `assignee null`, `items[1]` `Assigned` `{id, "Ada"}` ; huit clés partout | `testAc14UrsF0204ListShowsAssigneeObject` : rejoué, passe | Oui |
| U3-AC15 (URS-F-02.4) | Détail et statut | `GET /{id}` ; `PATCH /{id}/status` `closed` | 200 `assignee` Ada ; 200 `status closed`, même `assignee` | `testAc15UrsF0204ShowAndStatusCarryAssigneeObject` : rejoué, passe | Oui |
| U3-AC16 (URS-F-02.4) | Création | `POST /api/tickets` valide | 201, huit clés, `assignee === null` | `testAc16UrsF0204CreateStillYieldsNullAssignee` : rejoué, passe | Oui |
| U3-AC17 (URS-F-02.4) | Agent devenu inactif | Affecté, puis `setActive(false)` + `flush()` ; `GET /{id}` | `assignee` toujours `{id, "Ada"}` | `testAc17UrsF0204InactiveAgentStaysVisibleAsAssignee` : rejoué, passe | Oui |
| U3-AC18 (URS-N-01) | Journal : affectation | `TestHandler` ; PATCH `{"agentId": n}` | Enregistrement `info` `Ticket assignee changed`, contexte `{id, assignee: n}` | `testAc18UrsN01AssignmentIsLoggedAtInfoWithTicketIdAndNewValue` : rejoué, passe | Oui |
| U3-AC19 (URS-N-01) | Journal : désaffectation | PATCH `{"agentId": null}` | Contexte `{id, assignee: null}` | `testAc19UrsN01UnassignmentIsLogged` : rejoué, passe | Oui |
| U3-AC20 (URS-N-01) | Journal : rejets | 422 (inactif) puis 404 (inconnu), `disableReboot()` | Aucun enregistrement `Ticket assignee changed` | `testAc20UrsN01RejectedRequestsAreNotLogged` : rejoué, passe | Oui |
| U3-AC21 (URS-N-03) | Points d'entrée existants | `PATCH /{id}/status` `done`, `?status=bogus`, `PATCH /9999/status`, `GET /api/stats` | 422 corps exact, 400 même corps, 404 `Ticket not found`, cinq clés | `testAc21UrsN03ExistingEndpointsKeepTheirResponses` : rejoué, passe | Oui |

**Item U4** (`tests/Api/U4BreachedFilterTest.php`, 11 tests)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| U4-AC1 (URS-N-03) | Liste sans filtre | `open` dépassé, `closed` échu, `open` à venir ; `GET /api/tickets` | 200, `count === 3`, `createdAt` strictement décroissant | `testAc1UrsN03WithoutParameterBehaviourIsUnchangedNewestFirst` : rejoué, passe | Oui |
| U4-AC2 (URS-F-04.1) | `breached=true` | Cinq tickets dont `Legacy null due` (`due_at` mis à `NULL`) | 200, `count === 2`, titres `['Breached in progress', 'Old breached open']`, aucun `closed` ni `dueAt null` | `testAc2UrsF0401BreachedTrueReturnsOnlyBreachedTicketsNewestFirst` : rejoué, passe | Oui |
| U4-AC3 (URS-F-04.2) | `breached=false` | Mêmes cinq tickets | 200, `count === 3`, titres `['Legacy null due', 'Open not due', 'Closed past due']` ; union = liste complète, intersection vide | `testAc3UrsF0402BreachedFalseReturnsExactlyTheComplement` : rejoué, passe | Oui |
| U4-AC4 | `breached=true` sans résultat | `open` à venir, `closed` échu | 200, corps exactement `{"items": [], "count": 0}` | `testAc4UrsF0401BreachedTrueWithNoMatchReturnsEmptyList` : rejoué, passe | Oui |
| U4-AC5 (URS-F-04.2) | Valeur inconnue | `?breached=yes` | 400 JSON, corps exactement `{"error": "Invalid breached", "allowed": ["true", "false"]}` | `testAc5UrsF0402UnknownValueReturns400NamingAllowedValues` : rejoué, passe | Oui |
| U4-AC6 (URS-N-02) | Valeurs limites | `?breached=`, `1`, `0`, `True`, `TRUE`, `%20true`, `treu` | 400 JSON à chaque fois, `error` et `allowed` d'AC5 | `testAc6UrsN02EdgeValuesAreRejectedWith400Never500` : rejoué, passe | Oui |
| U4-AC7 (URS-N-02) | Valeur non scalaire | `?breached[]=true`, `?breached[a]=true` | 400 JSON, corps d'AC5 décodable | `testAc7UrsN02NonScalarValueIsJson400NotHtml` : rejoué, passe | Oui |
| U4-AC8 (URS-F-04.3) | Combinaison avec `status` | Quatre tickets ; cinq combinaisons dans les deux ordres | `Open breached`, `In progress breached`, liste vide, `Closed past due`, `Open fine` | `testAc8UrsF0403BreachFilterCombinesWithStatusFilter` : rejoué, passe | Oui |
| U4-AC9 (URS-N-02) | Valeurs invalides combinées | `status=done&breached=true`, `status=open&breached=maybe`, `status=done&breached=maybe` | 400 corps `status`, 400 corps `breached`, 400 corps `status` ; JSON partout | `testAc9UrsN02CombinedInvalidValuesStatusErrorTakesPrecedence` : rejoué, passe | Oui |
| U4-AC10 (URS-N-03) | Forme JSON, autres points d'entrée | Trois `GET` (sans, `breached=true`, combiné) ; `POST`, `GET /{id}`, `PATCH /{id}/status` `done`, `GET /api/stats` | Mêmes clés et même `items[0]` ; 201, 200, 422, cinq clés | `testAc10UrsN03JsonShapeUnchangedAndOtherEndpointsUntouched` : rejoué, passe | Oui |
| U4-AC11 | Prédicat du repository | `Future due` (T-3h, dueAt T+1h), `Past due` (T-2h, dueAt T-1h) ; clauses (a) à (f) | (a) DQL contient `t.dueAt` ; (b) `Past due` / `Future due` ; (c) horloge T+2h : les deux, `Past due` d'abord (plus récent), T-2h : aucun ; (d) frontière stricte ; (e) empilage dans les deux ordres ; (f) rétrocompatibilité | `testAc11RepositoryExposesOneReusableClockPinnedBreachPredicate` : rejoué, passe (clause (c) : ordre issu des horodatages de la fixture, voir A1) | Oui |

**Item U5** (`tests/Api/U5SlaReportTest.php`, 10 tests)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| U5-AC1 (URS-F-05.3) | Rapport sur table vide | `GET /api/reports/sla` sans ticket | 200 JSON, clés `generatedAt, priorities`, `generatedAt` `DATE_ATOM`, trois entrées `low, normal, high` à `0`, `breach_rate` flottant `0.0` | `testAc1UrsF0503EmptyTableReturns200WithThreeZeroEntries` : rejoué, passe | Oui |
| U5-AC2 (URS-F-05.1) | Calcul par priorité | Six tickets (2 `low`, 3 `normal`, 1 `high`) | `priorities` exactement `[low 2/1/50.0, normal 3/1/33.3, high 1/1/100.0]`, somme des totaux 6 | `testAc2UrsF0501TotalsBreachedAndRatesAreComputedPerPriority` : rejoué, passe | Oui |
| U5-AC3 (URS-F-05.1) | Arrondi | 6 `low` (1 dépassé), 3 `normal` (2 dépassés), 1 `high` clos | `low` 6/1/16.7, `normal` 3/2/66.7, `high` 1/0/0.0 (flottants) | `testAc3UrsF0501RateIsRoundedToOneDecimalAndNoBreachIsZeroPointZero` : rejoué, passe | Oui |
| U5-AC4 (URS-F-05.2) | Priorité sans ticket | Un seul `high` dépassé | Trois entrées : `low` 0/0/0.0, `normal` 0/0/0.0, `high` 1/1/100.0 ; pas d'`other` | `testAc4UrsF0502KnownPriorityWithNoTicketIsListedWithZeroRateAndNoOtherEntry` : rejoué, passe | Oui |
| U5-AC5 | Définition = U4 | Quatre `normal` (dépassé, clos échu, à venir, `due_at NULL`) | `normal` 4/1/25.0 ; `?breached=true` `count === 1` ; liste `count === 4` | `testAc5BreachDefinitionMatchesU4Filter` : rejoué, passe | Oui |
| U5-AC6 | Priorités inconnues | 2 `urgent`, 1 `critical`, 1 `normal` | Quatre entrées `low, normal, high, other` ; `other` exactement 3/1/33.3 ; `normal` 1/0/0.0 ; somme 4 | `testAc6UnknownPrioritiesAreAggregatedUnderTrailingOtherEntry` : rejoué, passe | Oui |
| U5-AC7 (URS-N-02) | Query string ignorée | `?breached=maybe`, `?status=done`, `?priority[]=low`, `?now[a]=1`, `?now=garbage` | 200 JSON, `priorities` identique à la référence | `testAc7UrsN02AnyQueryStringIsIgnoredAndNever500OrHtml` : rejoué, passe | Oui |
| U5-AC8 (URS-N-02) | Mauvaise méthode | `POST` et `DELETE /api/reports/sla` | 405 JSON, `error` chaîne, `Allow` contient `GET` | `testAc8UrsN02WrongMethodIsJson405NotHtmlNot500` : rejoué, passe | Oui |
| U5-AC9 (URS-N-03) | Points d'entrée existants | `GET /api/stats`, `GET /api/tickets`, `?breached=true`, `?breached=maybe`, `POST /api/tickets` | Cinq clés `total === 1` ; huit clés ; `count === 1` ; 400 `Invalid breached` ; 201 | `testAc9UrsN03ExistingEndpointsAreUntouched` : rejoué, passe | Oui |
| U5-AC10 | Service et repository | `countByPriority()` à vide puis sur `normal` ouvert / `high` clos ; `generate($now)` | `[]` ; `{normal: 1, high: 1}` ; `[]` ; T+2h `{normal: 1}` / `{high: 1}` ; rapport épinglé `normal` 1/1/100.0, `high` 1/0/0.0 ; liste ordonnée `low, normal, high` | `testAc10ServiceAndRepositoryCountThroughSharedClockPinnedPredicate` : rejoué, passe | Oui |

**Item FX** (`tests/Api/FXUrsNAcceptanceSweepTest.php`, 13 tests, plus l'amendement de `F0SlaFoundationTest`)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| FX-AC1 (URS-N-02) | `priority` manquante | `POST /api/tickets` avec le corps du scénario (`Export CSV is empty`) | 422 JSON, `errors.priority` seul ; liste `count === 0` | `testAc1UrsN02MissingPriorityReturns422` : rejoué, passe | Oui |
| FX-AC2 (URS-N-02) | Priorité inconnue | `urgent`, `High`, `LOW`, `""`, `null`, `123` | 422 `errors.priority` seul ; `count === 0` ; `Ticket::PRIORITIES === ['low', 'normal', 'high']` | `testAc2UrsN02UnknownPriorityReturns422AndUrgentIsNotAdded` : rejoué, passe | Oui |
| FX-AC3 (URS-N-02) | `title` / `description` invalides | Cinq corps `title`, trois corps `description`, `{}` | 422 `errors.title` seul ; 422 `errors.description` seul ; `{}` : trois clés ; `count === 0` | `testAc3UrsN02InvalidTitleAndDescriptionReturn422` : rejoué, passe | Oui |
| FX-AC4 (URS-N-03) | Création valide | `high` ; clé `colour` ; titre de 120 caractères | 201, huit clés, valeurs renvoyées, `status open`, `assignee null`, `dueAt - createdAt` = 28800 s ; 201 sans `colour` ; 201 | `testAc4UrsN03ValidCreateIsUnchanged` : rejoué, passe | Oui |
| FX-AC5 (URS-N-02) | JSON malformé, type de contenu | `{not json`, corps vide, `text/plain` | 400, 400, 415, tous JSON ; `count === 0` | `testAc5UrsN02MalformedJsonIs400AndNonJsonContentTypeIs415` : rejoué, passe | Oui |
| FX-AC6 (URS-N-02) | Ticket inconnu | `GET /api/tickets/9999`, `/0`, `/{existant}` | 404 JSON `{"error": "Ticket not found"}` x2 ; 200 huit clés | `testAc6UrsN02UnknownTicketIdOnShowIsJson404` : rejoué, passe | Oui |
| FX-AC7 (URS-N-02) | Id non numérique, méthode | `GET /api/tickets/abc` ; `DELETE /api/tickets/1` | 404 JSON ; 405 JSON, `Allow` contient `GET` | `testAc7UrsN02NonNumericIdAndWrongMethodAreJsonNever500` : rejoué, passe | Oui |
| FX-AC8 (URS-N-02) | Stats à vide | `GET /api/stats` sans ticket | 200 JSON, cinq clés, `0` partout, `closed_ratio == 0` | `testAc8UrsN02StatsOnEmptyTableReturns200WithZeros` : rejoué, passe | Oui |
| FX-AC9 (URS-N-03) | Stats avec tickets | Un `closed`, un `open`, un `in_progress` | `total 3`, `open 1`, `in_progress 1`, `closed 1`, `closed_ratio == 33.3` | `testAc9UrsN03StatsWithTicketsUnchanged` : rejoué, passe | Oui |
| FX-AC10 (section 6) | Scénario standard via le client de test | Les douze requêtes de `SimulateTrafficCommand`, dans l'ordre | Séquence exactement `[200, 201, 201, 422, 422, 200, 200, 404, 200, 422, 200, 200]`, JSON décodable partout | `testAc10Section6StandardScenarioReplayProducesNo500` : rejoué, passe. Séquence identique au rejeu de trafic en environnement `dev` (section 6) | Oui |
| FX-AC11 (section 6) | Commande console | `app:simulate-traffic --reset` via `CommandTester` | Code 0 ; aucune ligne `-> 5xx` ni `HTTP 5xx:` ; `HTTP 422: 3` ; `HTTP 404: 1` | `testAc11Section6ConsoleCommandReportsNo500` : rejoué, passe | Oui |
| FX-AC12 (URS-N-01) | Balayage du journal | Affectation, désaffectation, désactivation dans un même conteneur | Deux `Ticket assignee changed` (contextes exacts), un `Agent activation changed` | `testAc12UrsN01AuditLogSweepAssignUnassignDeactivate` : rejoué, passe | Oui |
| FX-AC13 (URS-F-03.2, URS-N-03) | Amendement de F0 AC11 | `POST /api/tickets` `urgent` ; `Ticket::slaTargetHours('urgent')`, `('')`, `('LOW')` | 422 `errors.priority` ; 24 dans les trois cas ; aucune autre ligne du fichier modifiée | `F0SlaFoundationTest::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget` (amendé) : rejoué, passe. Clause « aucune autre ligne » : revue de code du diff `git diff 46cb147..HEAD -- tests/Api/F0SlaFoundationTest.php` (`+3 -2`, lignes 210 à 214 uniquement) | Oui (test rejoué ; clause d'exclusivité par revue de code) |
| FX-AC14 (URS-N-03) | Suites de base et balayage | `TicketApiTest` intact ; `GET /api/tickets`, `GET /{id}`, `PATCH /{id}/status` `closed`, `PATCH /{id}/assignee` `null`, `PATCH /{id}/status` `done` | Fichier identique ; 200 huit clés x4 ; 422 corps exact | `testAc14UrsN03TicketEndpointsKeepTheirKeysAndStatusBodies` : rejoué, passe ; les trois tests de `TicketApiTest` rejoués, passent. Clause « octet pour octet identique » : revue de code, `git diff 46cb147..HEAD -- tests/Api/TicketApiTest.php` vide | Oui (test rejoué ; clause d'intégrité par revue de code) |

Bilan : 100 critères, 100 couverts par au moins un test rejoué et passant ; 2 clauses
(FX AC13 « aucune autre ligne », FX AC14 « fichier identique ») complétées par revue de
code du diff ; 0 critère en échec ; 0 critère sans test.

## 6. Tests End-to-End

Rejeu de `tools/php bin/console app:simulate-traffic --reset` le 14 octobre 2026 sur
`05c7f08`.

**Scénario** : réinitialisation du schéma de `var/data_dev.db` (par `SchemaTool`, table
`ticket` vide), puis douze requêtes HTTP dans l'ordre (fixture de
`FXUrsNAcceptanceSweepTest::SCENARIO`, identique à celle de la commande) :
`GET /api/stats` (table vide), `POST /api/tickets` `high`, `POST /api/tickets` `low`,
`POST /api/tickets` sans `priority`, `POST /api/tickets` `urgent`, `GET /api/tickets`,
`GET /api/tickets/1`, `GET /api/tickets/9999`, `PATCH /api/tickets/1/status`
`in_progress`, `PATCH /api/tickets/1/status` `done`, `GET /api/tickets?status=open`,
`GET /api/stats`.

**Codes HTTP obtenus** (séquence `200, 201, 201, 422, 422, 200, 200, 404, 200, 422, 200, 200`) :

| Code | Nombre |
|------|--------|
| 200 | 6 |
| 201 | 2 |
| 404 | 1 |
| 422 | 3 |
| 5xx | **0** |

**Journal** : 61 lignes ajoutées à `var/log/dev.log` (lignes 4388 à 4448), dont
**0 ligne `CRITICAL`**, 0 ligne `ERROR`, 0 ligne `WARNING`. Les trois `CRITICAL` relevés
dans le dossier 0.2.0 (`DivisionByZeroError` dans `TicketStats.php`, `Undefined array key
"priority"` et `toArray() on null` dans `TicketController.php`) ont disparu.

Ce résultat est **identique** à celui consigné par le vérificateur de l'item FX
(`verdict-1.json` : `200:6 201:2 404:1 422:3, no 5xx, 61 lines appended, zero CRITICAL,
zero ERROR`) et à la séquence attendue par FX AC10 et AC11. Le rejeu de l'enregistreur ne
contredit donc pas le verdict ; aucune anomalie bloquante n'est ouverte à ce titre.

**Ce que ce rejeu prouve** : la section 6 de l'URS-002 (scénario standard sans 5xx) pour
l'item FX ; les trois corrections de FX (`GET /api/stats` à vide en 200, `POST
/api/tickets` sans `priority` et avec `urgent` en 422, `GET /api/tickets/9999` en 404) ;
le filtre R1 (`GET /api/tickets?status=open` en 200) ; l'absence de régression de code
HTTP sur les points d'entrée ticket et stats après les modifications de `Ticket::toArray()`
(U3) et de `TicketController::list()` (R1, U4).

**Ce qu'il ne prouve pas** : les items U1, U2, U3, U4 (sauf la non-régression de la liste)
et U5, dont les points d'entrée (`/api/agents`, `/api/agents/{id}`,
`/api/tickets/{id}/assignee`, `?breached=`, `/api/reports/sla`) ne figurent pas dans le
scénario ; le contenu des réponses (clé `assignee`, corps d'erreur JSON) ; les lignes
d'audit URS-N-01 dans `var/log/dev.log` sous trafic réel ; la migration (aucune dans la
plage, et le `--reset` recrée le schéma par `SchemaTool`).

## 7. Traçabilité / Logs

**Plage de commits** : `46cb147..05c7f08` sur la branche `build/urs-002` (premier commit de
la plage `136598d`, dernier `05c7f08`). Commits de code des items, dans l'ordre de
livraison : R1 `5441fa6`, U1 `b78d25e`, U3 `a44175d`, U2 `87b6736`, U4 `8fb7f22`, U5
`625b2ed`, FX `51f343d`. Commit rejoué : `05c7f08`
(`05c7f082e410b187b65dff7cd4f11367fbade661`, 2026-10-14 20:46:30 +0200). Diff applicatif de
la plage (`git diff --stat 46cb147..HEAD -- src migrations tests`) : 22 fichiers, 2899
insertions, 12 suppressions ; `migrations/` : aucun fichier.

- `src/` (13 fichiers) : `Controller/AgentController.php` (nouveau),
  `Controller/SlaReportController.php` (nouveau), `Controller/TicketController.php`,
  `Dto/AssignTicketRequest.php`, `Dto/CreateAgentRequest.php`, `Dto/CreateTicketRequest.php`,
  `Dto/UpdateAgentActiveRequest.php` (nouveaux), `Entity/Ticket.php` (1 ligne),
  `EventListener/ApiExceptionListener.php` (nouveau), `Repository/AgentRepository.php`,
  `Repository/TicketRepository.php`, `Service/SlaReport.php` (nouveau),
  `Service/TicketStats.php` (2 lignes).
- `tests/` (9 fichiers) : sept nouvelles classes `R1...`, `U1...`, `U2...`, `U3...`,
  `U4...`, `U5...`, `FX...Test.php`, `Api/F0SlaFoundationTest.php` (`+3 -2`),
  `bootstrap.php` (`+15`). `Api/TicketApiTest.php`, `Api/ApiTestCase.php` et
  `Unit/F0SlaFoundationMigrationTest.php` : diff vide.
- Hors `src`/`tests` : `composer.json` (`+2` : `symfony/property-access`,
  `symfony/property-info`), `composer.lock`, `symfony.lock`, `config/reference.php`,
  `config/packages/property_info.yaml` (nouveau), `.env.test` (`+3`, `DATABASE_URL` avec
  `TEST_TOKEN`), `.gitignore`, `CLAUDE.md`, `.claude/` (agents, hooks, skills, settings),
  `approvals/`, `release/unreleased/*.md`, `requests/done/001-...`, `requests/done/003-...`,
  `reports/`, `specs/`, `docs/user/`.

**Suite PHPUnit** (14 octobre 2026, 20:47, `05c7f08`) :

```
tools/php vendor/bin/phpunit
PHPUnit 13.4.2 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.5
Configuration: D:\Sandbox\yt-30-10-2026\ticketflow\phpunit.dist.xml
...............................................................  63 / 117 ( 53%)
......................................................          117 / 117 (100%)
Time: 00:06.179, Memory: 44.00 MB
OK (117 tests, 1128 assertions)
```

Répartition : `TicketApiTest` 3, `F0SlaFoundationTest` 13, `F0SlaFoundationMigrationTest` 2,
`R1FilterTicketsByStatusTest` 10, `U1AgentsCreateListTest` 16, `U2AgentActiveFlagTest` 18,
`U3TicketAssigneeTest` 21, `U4BreachedFilterTest` 11, `U5SlaReportTest` 10,
`FXUrsNAcceptanceSweepTest` 13 (total 117). Aucun test en échec, ignoré, incomplet ou
risqué ; aucune dépréciation signalée (la ligne de résumé est `OK`, sans « issues »).
Base de test : fichier `var/data_test_<pid>.db` propre au processus
(`tests/bootstrap.php`), supprimé en fin d'exécution.

**Trafic simulé** (14 octobre 2026, 20:47, `05c7f08`) :

```
tools/php bin/console app:simulate-traffic --reset
Schema reset: ticket table is empty.
GET    /api/stats                   -> 200
POST   /api/tickets                 -> 201
POST   /api/tickets                 -> 201
POST   /api/tickets                 -> 422
POST   /api/tickets                 -> 422
GET    /api/tickets                 -> 200
GET    /api/tickets/1               -> 200
GET    /api/tickets/9999            -> 404
PATCH  /api/tickets/1/status        -> 200
PATCH  /api/tickets/1/status        -> 422
GET    /api/tickets?status=open     -> 200
GET    /api/stats                   -> 200

HTTP 200: 6
HTTP 201: 2
HTTP 404: 1
HTTP 422: 3
Logs written to var/log/dev.log
```

Lignes ajoutées à `var/log/dev.log` : 61 (de la ligne 4388 à la ligne 4448) ; `grep -c
CRITICAL` sur ces lignes : 0 ; aucune ligne `ERROR` ni `WARNING`.

**Migration** : non rejouée, la plage ne contenant aucune migration
(`git diff --name-only 46cb147..HEAD -- migrations` : vide ; `migrations/` contient
toujours `Version20261014130059.php` et `Version20261014133137.php`, rejouées pour 0.2.0).

**Tours du vérificateur** (un tour par item, tous `PASS` ; suite cumulée au moment du
verdict) :

| Item | Tour | Verdict | Suite au verdict | Notes du vérificateur (extraits) |
|------|------|---------|------------------|----------------------------------|
| R1 | 1 (`verdict-1.json`) | `PASS` | `OK (28 tests, 224 assertions)` | AC1 à AC10 : une méthode chacun. Lecture brute de la query string au lieu de `MapQueryParameter` (déviation prévue par la spécification, URS-N-02). Diff limité à `TicketController.php` et `TicketRepository.php`. Trois `CRITICAL` du trafic, antérieurs et hors périmètre. |
| U1 | 1 | `PASS` | `OK (44 tests, 380 assertions)` | AC1 à AC16 : une méthode chacun. **Signalement de périmètre** : `composer.json`, `composer.lock`, `symfony.lock`, `config/reference.php` modifiés et `config/packages/property_info.yaml` ajouté (recette Flex de `symfony/property-access` + `symfony/property-info`, requis par `#[MapRequestPayload]`). Accepté par l'orchestrateur le 14 octobre 2026 (voir A4). Trois `CRITICAL` antérieurs. |
| U3 | 1 | `PASS` | `OK (65 tests, 529 assertions)` | AC1 à AC21 : une méthode chacun (statut, `Content-Type`, corps, rechargement, journal). DTO `AssignTicketRequest` typé `mixed` + `Assert\Type('int')` pour éviter une dépréciation PHP sur un flottant (style uniquement, voir A5). Trois `CRITICAL` antérieurs, aucun dans `assign()` ni `Ticket::toArray()`. |
| U2 | 1 | `PASS` | `OK (83 tests, 686 assertions)` | AC1 à AC18 : une méthode chacun ; assertions de journal par `TestHandler` avec contexte exact et unicité. Diff : `AgentController.php` (+18), DTO `UpdateAgentActiveRequest`. Trois `CRITICAL` antérieurs. |
| U4 | 1 | `PASS` | `OK (94 tests, 795 assertions)` | AC1 à AC11 : une méthode chacun. Diff : `TicketController::list()` (+10/-1), `TicketRepository` (+37/-2, `withBreached()`, `findNewestFirst()` étendu). Lecture brute de la query string, cohérente avec R1. Trois `CRITICAL` antérieurs. |
| U5 | 1 | `PASS` | `OK (104 tests, 921 assertions)` | AC1 à AC10 : une méthode chacun. `TicketRepository::countByPriority()`, service `SlaReport`, contrôleur invocable, `JSON_PRESERVE_ZERO_FRACTION`. Le simulateur n'appelle pas `/api/reports/sla`. Trois `CRITICAL` antérieurs. |
| FX | 1 | `PASS` | `OK (117 tests, 1128 assertions)` | 13 méthodes pour AC1 à AC12 et AC14 ; AC13 = amendement de `F0SlaFoundationTest::testAc11`. Rejeu du trafic : `200:6 201:2 404:1 422:3`, aucun 5xx, 61 lignes, zéro `CRITICAL`, zéro `ERROR`. Diff : `TicketController.php` (+6/-4), `TicketStats.php` (+2/-2), DTO `CreateTicketRequest` ; fichiers hors périmètre identiques. `remaining_criticals_out_of_scope` : vide. `requests/002` reste ouverte. |

**Rapports du testeur** (`tester.md`) : R1 `OK (10 tests, 64 assertions)` seul, suite
`OK (28 tests, 224 assertions)` ; U1 « à la remise : 16 tests, 9 échecs (`no supporting
normalizer found`, côté implémentation, avant la fin du développement), AC10 à AC16
passants » (voir A3) ; U3 « OK avec 1 dépréciation (flottant 1.5 vers int) sur AC11 ;
suite 65 tests, 529 assertions, 1 dépréciation » (voir A5) ; U2 `OK (18 tests, 157
assertions)` seul, suite `OK (83 tests, 686 assertions)` ; U4 `OK (11 tests, 109
assertions)` seul, suite `OK (94 tests, 795 assertions)`, AC11(c) « affirme l'ordre
produit par les horodatages de la fixture, la formulation littérale de la spécification
les contredisant » (voir A1) ; U5 `OK (10 tests, 126 assertions)` seul, « suite complète
instable (`table agent already exists`, 404 sur lignes fraîches) car l'agent développeur
exécutait PHPUnit en parallèle sur le `var/data_test.db` partagé » (voir A2) ; FX
`OK (13 tests, 210 assertions)` seul, suite `OK (117 tests, 1128 assertions)`. Tous
déclarent « critères non couverts : aucun ».

## 8. Anomalies détectées

| ID | Description | Gravité | Statut / Action |
|----|-------------|---------|-----------------|
| A1 | U4 AC11, clause (c) : la spécification annonce « `Future due` first (newest createdAt first) », mais sa propre fixture donne `createdAt` T-3h à `Future due` et T-2h à `Past due` ; le plus récent est donc `Past due`. Le test affirme `['Past due', 'Future due']`, conforme aux horodatages et au tri « plus récent d'abord » prouvé par ailleurs (U4 AC1, AC2). Incohérence de rédaction de la spécification, signalée par le testeur, acceptée par le vérificateur. | Mineure | Documentée. Corriger la phrase de `specs/U4-breached-filter.md` lors d'une prochaine révision ; aucune action sur le code. |
| A2 | Instabilité de la suite complète pendant le développement de U5 (`table agent already exists`, 404 sur lignes fraîches) due au partage d'un unique fichier `var/data_test.db` entre agents concurrents. Corrigée par le commit d'infrastructure `b04fff4` (`tests/bootstrap.php`, `.env.test` : un fichier `var/data_test_<pid>.db` par processus). Ce commit n'a ni spécification ni verdict de vérificateur. | Mineure | Corrigée. Le rejeu de l'enregistreur (un seul processus) est stable : `OK (117 tests, 1128 assertions)`. Aucune action supplémentaire exigée ; la stabilité sous exécution concurrente n'est pas prouvée par ce dossier. |
| A3 | U1 : à la remise du testeur, 9 des 16 tests échouaient (`no supporting normalizer found`, implémentation inachevée, développement parallèle). Le seul tour du vérificateur (`PASS`, `OK (44 tests, 380 assertions)`) et le rejeu de l'enregistreur attestent le passage au commit `b78d25e` et à `05c7f08`. | Mineure | Sans action. Mentionnée pour que la chronologie des preuves soit complète : la preuve de passage de U1 AC1 à AC9 repose sur le verdict et le rejeu, pas sur le rapport du testeur. |
| A4 | U1 : la spécification excluait toute modification de `composer.json` et `config/` ; l'item a néanmoins ajouté `symfony/property-access` et `symfony/property-info` (recette Flex, `config/packages/property_info.yaml`, `config/reference.php`, `composer.lock`, `symfony.lock`), nécessaires au mapping `#[MapRequestPayload]` demandé par la spécification elle-même. Signalement de périmètre du vérificateur, accepté par l'orchestrateur le 14 octobre 2026. | Mineure | Acceptée et documentée. Dépendances applicatives nouvelles à faire figurer dans le rapport officiel. |
| A5 | U3 AC11 : à la remise, une dépréciation PHP (conversion implicite du flottant `1.5` en entier) était émise ; le DTO `AssignTicketRequest` type `agentId` en `mixed` avec `Assert\Type('int')` au lieu de `?int` pour l'éviter (remarque de style du vérificateur). Au rejeu, la suite ne signale aucune dépréciation. | Mineure | Sans action ; comportement observable prouvé par U3 AC10 et AC11. |
| A6 | Changement de contrat sur `POST /api/tickets` : une priorité hors de `['low', 'normal', 'high']` (dont `urgent`, envoyée par le scénario standard) reçoit désormais 422 (FX AC2), alors que 0.2.0 l'acceptait avec une cible SLA de 24 h (F0 AC11, URS-F-03.2). Le repli entité `Ticket::slaTargetHours()` est conservé (FX AC13). Changement voulu par la spécification FX au titre d'URS-N-02 ; la demande humaine `requests/002-urgent-priority.md` reste ouverte. | Mineure | Documentée. À signaler aux consommateurs de l'API ; traiter `requests/002` par un item dédié définissant la cible SLA d'`urgent`. |
| A7 | Le scénario standard de `app:simulate-traffic` n'exerce aucun point d'entrée des items U1 à U5 (`/api/agents`, `/api/agents/{id}`, `/api/tickets/{id}/assignee`, `?breached=`, `/api/reports/sla`). La preuve end-to-end de ces items se limite à la suite PHPUnit (remarque du vérificateur U5). | Mineure | Documentée (voir « Périmètre non couvert »). Étendre le scénario du simulateur lors d'un item ultérieur pour couvrir le module SLA sous trafic. |
| A8 | **Antérieur à cette version** (dossier 0.2.0, A1). `GET /api/stats` sur table vide renvoyait 500 (`DivisionByZeroError`, `TicketStats.php`). Corrigé par FX ; prouvé par `FXUrsNAcceptanceSweepTest::testAc8UrsN02StatsOnEmptyTableReturns200WithZeros` (rejoué, passe) et par la première requête du trafic rejoué (200). | Majeure (antérieure) | **Fermée en 0.3.0.** |
| A9 | **Antérieur à cette version** (dossier 0.2.0, A2). `POST /api/tickets` sans `priority` renvoyait 500 (`Undefined array key "priority"`). Corrigé par FX ; prouvé par `testAc1UrsN02MissingPriorityReturns422` (rejoué, passe) et par la quatrième requête du trafic rejoué (422). | Majeure (antérieure) | **Fermée en 0.3.0.** |
| A10 | **Antérieur à cette version** (dossier 0.2.0, A3). `GET /api/tickets/9999` renvoyait 500 (`toArray() on null`). Corrigé par FX ; prouvé par `testAc6UrsN02UnknownTicketIdOnShowIsJson404` (rejoué, passe) et par la huitième requête du trafic rejoué (404). | Majeure (antérieure) | **Fermée en 0.3.0.** |

Synthèse : 10 anomalies, dont 3 majeures antérieures (A8 à A10) **fermées** par cette
version et vérifiées au rejeu ; 7 anomalies ouvertes ou documentées : 0 bloquante,
0 majeure, 7 mineures (A1 à A7), dont deux déjà corrigées (A2, A5).

## 9. Conclusion

**La version 0.3.0 est validée au commit `05c7f08`.**

Les cent critères d'acceptation des sept items (R1, U1, U2, U3, U4, U5, FX) sont chacun
couverts par au moins un test rejoué par l'enregistreur le 14 octobre 2026, et la suite
complète est verte (`OK (117 tests, 1128 assertions)`), sans dépréciation. Le rejeu du
trafic simulé donne `200:6 201:2 404:1 422:3`, **aucun 5xx et zéro `CRITICAL`**, résultat
strictement identique à celui consigné par le vérificateur de l'item FX et à la séquence
exigée par la section 6 de l'URS-002 ; les trois erreurs 500 antérieures du dossier 0.2.0
sont fermées. La plage ne contient aucune migration. Les sept verdicts (un tour `PASS`
chacun) sont cohérents avec le rejeu. Les deux seules clauses sans test (intégrité de
`TicketApiTest`, exclusivité de l'amendement de `F0SlaFoundationTest`) sont établies par
revue du diff et n'entament pas la validation.

Points d'attention avant la validation finale et pour les versions suivantes :

1. Signaler aux consommateurs de l'API le changement de contrat sur `POST /api/tickets`
   (A6 : priorité inconnue, dont `urgent`, désormais refusée en 422) et traiter
   `requests/002-urgent-priority.md` par un item dédié.
2. Faire figurer dans le rapport officiel les deux dépendances ajoutées par U1 (A4 :
   `symfony/property-access`, `symfony/property-info`).
3. Étendre le scénario de `app:simulate-traffic` aux points d'entrée du module SLA (A7)
   pour disposer d'une preuve end-to-end des items U1 à U5 et des lignes d'audit URS-N-01
   dans `var/log/dev.log`.
4. Corriger la formulation de U4 AC11(c) dans la spécification (A1) et, à l'occasion,
   ajouter un test exerçant `ON DELETE SET NULL` dès qu'un item autorise la suppression
   d'un agent (point ouvert depuis 0.2.0).

Ce dossier est un projet complémentaire au rapport de validation officiel ; il ne s'y
substitue pas et ne vaut pas, à lui seul, autorisation de mise en production.
