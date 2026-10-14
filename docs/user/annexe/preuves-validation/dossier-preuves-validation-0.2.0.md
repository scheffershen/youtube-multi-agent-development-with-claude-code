---
sidebar_position: 0
id: dossier-preuves-validation-0-2-0
title: Version 0.2.0
---

# Dossier de Preuves de Validation Fonctionnelle - Version 0.2.0

Date : 14 octobre 2026. Version précédente : 0.1.0. Commit de référence : `291c830`.

## 1. Contexte

La version 0.2.0 est la première version issue du cycle de construction URS (`/build`).
Elle contient un seul item, le socle du module SLA :

- **F0 - Socle SLA** (spécification `specs/F0-sla-foundation.md`, item de type
  *foundation*, commit `c63c5ff`). L'item pose la persistance et la base de tests sur
  lesquelles reposent les items suivants du module SLA (U1 à U5). Il n'ajoute **aucun
  point d'entrée HTTP**. Concrètement :
  - nouvelle entité `Agent` (`id`, `name`, `email` unique, `active` par défaut à
    `true`) et son dépôt `AgentRepository` (URS-F-01.1) ;
  - relation optionnelle `Ticket::$assignee` vers `Agent` (colonne `assignee_id`,
    `ON DELETE SET NULL`), accesseurs `getAssignee()` / `setAssignee()` (URS-F-02.1) ;
  - échéance `Ticket::$dueAt` calculée une seule fois à la construction du ticket à
    partir de `createdAt` et de la priorité : `low` 72 h, `normal` 24 h, `high` 8 h
    (URS-F-03.1), priorité inconnue ramenée à `normal` (URS-F-03.2), échéance jamais
    recalculée et sans `setDueAt()` public (URS-F-03.3) ;
  - deux nouvelles clés dans chaque réponse ticket : `dueAt` (ISO 8601, `DATE_ATOM`,
    URS-F-03.4) et `assignee` (toujours `null` dans cette version), toutes les clés
    existantes étant conservées (URS-N-03) ;
  - une migration Doctrine `Version20261014133137` (table `agent`, colonnes
    `assignee_id` et `due_at` sur `ticket`) ;
  - une classe de base de tests `tests/Api/ApiTestCase.php` (`postJson()`,
    `patchJson()`, `body()`, `createAgent()`, `createTicket()`) sur laquelle la suite
    existante `TicketApiTest` est déplacée.

L'item est rattaché au document d'exigences `docs/urs/URS-002-sla-module.md`. La
spécification ne référence aucune demande humaine du dossier `requests/` : l'item
provient de la décomposition de l'URS, pas d'une demande utilisateur.

Les autres commits de la plage (`0c82835`, `7f2a346`, `0c5ad40`, `291c830`) ne
touchent ni `src/`, ni `migrations/`, ni `tests/` : le diff applicatif de la plage
`ce6a1a4..291c830` se limite aux huit fichiers de l'item F0.

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

Vérifier que la version 0.2.0 :

**Item F0 - Socle SLA**

- AC1 : applique `doctrine:migrations:migrate -n` sans erreur sur une base contenant déjà
  des lignes de l'ancien schéma et sur une base vide (les deux migrations dans l'ordre) ;
  conserve les lignes `ticket` existantes avec `assignee_id` et `due_at` à `NULL` ; crée
  la table `agent` avec un index unique sur `email`.
- AC2 : persiste un `Agent` et un `Ticket` (avec `createdAt` et `dueAt` explicites et un
  assigné), les recharge par identifiant et relit des valeurs identiques.
- AC3 : conserve la suite existante verte (`testCreateAndListTicket`,
  `testChangeStatus`, `testStatsWithTickets`) avec leurs assertions inchangées.
- AC4 : renvoie sur `GET /api/tickets` toutes les clés existantes plus `dueAt` (chaîne
  `DATE_ATOM`) et `assignee` (présent, valeur `null`).
- AC5 : expose `tests/Api/ApiTestCase.php`, abstraite, héritée de `WebTestCase`, avec
  les aides `postJson()`, `patchJson()`, `body()`, `createAgent()`, `createTicket()`
  en `protected`, et `TicketApiTest` qui en hérite sans changer ses assertions.
- AC6 (URS-F-01.1) : `createAgent('Ada', 'ada@example.test')` produit un agent avec
  identifiant non nul, `isActive() === true` par défaut et un `toArray()` exact.
- AC7 (URS-F-01.1) : un second `Agent` avec le même email provoque
  `UniqueConstraintViolationException` au `flush()`.
- AC8 (URS-F-02.1) : l'assigné est `null` par défaut, survit à l'affectation après
  rechargement, et redevient `null` après `setAssignee(null)`.
- AC9 (URS-F-03.1) : `POST /api/tickets` avec `low`, `normal`, `high` renvoie 201 avec
  `dueAt - createdAt` égal à 72 h, 24 h et 8 h.
- AC10 (URS-F-03.1) : le constructeur dérive `dueAt` de `createdAt` + cible SLA ;
  `Ticket::slaTargetHours()` renvoie 72, 24, 8.
- AC11 (URS-F-03.2) : la priorité `urgent` (et `''`, `LOW`) reçoit la cible `normal`
  (24 h) ; `POST /api/tickets` avec `urgent` renvoie 201.
- AC12 (URS-F-03.3) : une échéance explicite est relue telle quelle depuis `due_at`,
  jamais recalculée à partir de la priorité ; `Ticket` n'expose pas de `setDueAt()`
  public.
- AC13 (URS-F-03.4) : `POST /api/tickets` et `GET /api/tickets/{id}` renvoient un
  `dueAt` ISO 8601 identique pour le même ticket.
- AC14 (URS-N-03) : les quatre points d'entrée ticket renvoient exactement le jeu de
  clés `id, title, description, priority, status, createdAt, dueAt, assignee` ;
  `GET /api/stats` renvoie exactement `total, open, in_progress, closed, closed_ratio`.
- AC15 (URS-N-03) : `GET /api/tickets` sans ticket renvoie 200 `{"items": [], "count": 0}`.

### Périmètre non couvert

- **Aucun parcours navigateur** : TicketFlow est une API JSON ; aucune interface
  graphique n'est exercée.
- **Aucun déploiement réel ni service externe** : toutes les preuves sont produites en
  local sur SQLite (`var/data_dev.db`, `var/data_test.db`, fichiers temporaires).
- **Migration sur le fichier `var/data_dev.db` d'origine** : la spécification demande
  AC1 sur « la base de développement actuelle avec ses trois tickets ». Le test
  `testAc1MigrateKeepsExistingDevRows` reconstruit cette situation dans un fichier
  SQLite temporaire (ancien schéma, trois lignes insérées, puis migration). Le fichier
  `var/data_dev.db` lui-même n'a pas été inspecté avant le rejeu, et le rejeu du trafic
  (`--reset`) le réinitialise par `SchemaTool`, sans passer par les migrations.
- **`ON DELETE SET NULL`** : SQLite n'applique cette clause que si
  `PRAGMA foreign_keys` est actif ; aucun test ne supprime un agent. La clause est
  présente dans le schéma généré (voir section 7) mais son effet n'est pas exercé.
- **Contenu JSON sous trafic simulé** : le simulateur ne vérifie que les codes HTTP ;
  la présence et le format de `dueAt` / `assignee` ne sont prouvés que par la suite
  PHPUnit.
- **Comportements hors item** : aucun point d'entrée agent, affectation, dépassement
  ou rapport SLA (items U1 à U5) n'existe dans cette version ; la priorité
  `Ticket::PRIORITIES` n'est pas contrainte.
- **Défauts antérieurs** : les trois erreurs 500 déclenchées par le simulateur
  (`GET /api/stats` sur table vide, `POST /api/tickets` sans `priority`,
  `GET /api/tickets/9999`) sont antérieures à cette version et explicitement hors
  périmètre de F0 (voir section 8).

## 4. Méthodologie

1. **Spécification avant code.** `specs/F0-sla-foundation.md` a été rédigée par l'agent
   spec-writer à partir de l'URS-002, avec quinze critères d'acceptation numérotés
   (AC1 à AC15) et les identifiants URS associés, avant toute ligne de code.
2. **Tests écrits depuis la spécification.** L'agent testeur a écrit
   `tests/Api/F0SlaFoundationTest.php` (AC2, AC4 à AC15) et
   `tests/Unit/F0SlaFoundationMigrationTest.php` (AC1) à partir de la seule
   spécification, sans accès au code produit par l'agent développeur, qui travaillait
   en parallèle.
3. **Vérification indépendante.** L'agent vérificateur a confronté le code et les tests
   à la spécification en **deux tours** : tour 1 `FAIL`, tour 2 `PASS` (détail en
   section 7). Le commit `c63c5ff` n'a été créé qu'après le `PASS`.
4. **Rejeu par l'enregistreur.** Le présent dossier repose sur un rejeu effectué le
   14 octobre 2026 sur le commit `291c830` (HEAD, arbre de travail propre,
   `git status --short` vide) :
   - `tools/php vendor/bin/phpunit` ;
   - `tools/php bin/console app:simulate-traffic --reset` ;
   - `tools/php bin/console doctrine:migrations:migrate -n` sur un fichier SQLite vide
     (via `DATABASE_URL`), puisque la plage contient une migration.
5. **Lecture des preuves uniquement.** L'enregistreur a lu les verdicts
   (`reports/items/F0/verdict-1.json`, `verdict-2.json`), le rapport du testeur
   (`reports/items/F0/tester.md`), la spécification et les fichiers de test. Il n'a lu
   ni le rapport du développeur, ni les fragments de changelog, ni les messages de
   commit. Lorsqu'une clause n'est couverte par aucun test (AC3 et AC5, clause
   « assertions inchangées »), elle est marquée « revue de code » et porte sur la
   lecture effective du diff `git diff ce6a1a4..HEAD -- tests/Api/TicketApiTest.php`.

L'existence d'un test dans le dépôt n'est pas traitée comme une preuve de passage ; seul
le résultat du rejeu de l'étape 4 fait foi.

## 5. Tests utilisateurs

Statut rejoué le 14 octobre 2026 sur `291c830` : `OK (18 tests, 160 assertions)`.

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| F0-AC1 | Migration `Version20261014133137` | Base SQLite temporaire migrée jusqu'à `Version20261014130059`, trois lignes `ticket` insérées (dont priorité `urgent`), puis `doctrine:migrations:migrate -n` ; même commande sur un fichier vide | Les trois lignes conservées, `assignee_id` et `due_at` à `NULL` ; table `agent` avec index unique sur `email` ; les deux migrations passent sur base vide | `F0SlaFoundationMigrationTest::testAc1MigrateKeepsExistingDevRows` et `testAc1MigrateRunsBothMigrationsOnEmptyDatabase` : rejoués, passent. Migration également rejouée à la main sur un fichier vide : `2 migrations executed, 9 sql queries`, `[OK]` (section 7) | Oui |
| F0-AC2 | Persistance `Agent` / `Ticket` | Un agent (`active = false`) et un ticket (`createdAt`, `dueAt` explicites, assigné) persistés, `clear()`, rechargés par id | Toutes les valeurs relues identiques, `getAssignee()->getId()` égal à l'id de l'agent | `F0SlaFoundationTest::testAc2EntitiesRoundTripThroughPersistence` : rejoué, passe (sur `ApiTestCase`, un `WebTestCase`, et non un `KernelTestCase` nu ; voir A5) | Oui |
| F0-AC3 | Non-régression de la suite existante | `tools/php vendor/bin/phpunit` | Suite verte ; `testCreateAndListTicket`, `testChangeStatus`, `testStatsWithTickets` gardent leurs assertions | Les trois méthodes de `TicketApiTest` rejouées dans la suite : passent. Clause « assertions inchangées » : revue de code du diff de `tests/Api/TicketApiTest.php` sur la plage (`+1 -32` : seuls la classe parente et les membres dupliqués `setUp()`, `$client`, `postJson()`, `body()` changent ; les corps des trois tests sont intacts) | Oui |
| F0-AC4 | Liste des tickets | `POST /api/tickets` puis `GET /api/tickets` | `count` et `items[0]` avec `id`, `title`, `description`, `priority`, `status`, `createdAt`, plus `dueAt` parsable en `DATE_ATOM` et `assignee` présent à `null` | `F0SlaFoundationTest::testAc4ListKeepsExistingKeysAndAddsDueAtAndAssignee` : rejoué, passe | Oui |
| F0-AC5 | Base de tests `ApiTestCase` | Inspection par réflexion de `ApiTestCase` et de `TicketApiTest` | Classe abstraite héritée de `WebTestCase`, cinq aides `protected`, `TicketApiTest` en hérite sans changer ses assertions | `F0SlaFoundationTest::testAc5ApiTestCaseShapeAndTicketApiTestExtendsIt` : rejoué, passe. Clause « sans changer ses assertions » : revue de code (même diff qu'en F0-AC3) | Oui |
| F0-AC6 | Création d'agent (URS-F-01.1) | `createAgent('Ada', 'ada@example.test')` | Id non nul, `isActive() === true`, `toArray()` égal à `{id, name: Ada, email: ada@example.test, active: true}` | `F0SlaFoundationTest::testAc6UrsF0101CreateAgentDefaultsAndToArray` : rejoué, passe | Oui |
| F0-AC7 | Unicité de l'email (URS-F-01.1) | Deux agents avec le même email, `flush()` | `Doctrine\DBAL\Exception\UniqueConstraintViolationException` | `F0SlaFoundationTest::testAc7UrsF0101DuplicateEmailThrowsUniqueConstraintViolation` : rejoué, passe | Oui |
| F0-AC8 | Affectation d'un ticket (URS-F-02.1) | `createTicket()` sans assigné, puis `setAssignee($agent)`, puis `setAssignee(null)`, avec `flush()` + `clear()` + rechargement à chaque étape | `null`, puis l'id de l'agent, puis `null` | `F0SlaFoundationTest::testAc8UrsF0201AssigneeNullAssignedAndUnassigned` : rejoué, passe | Oui |
| F0-AC9 | Échéance par priorité via l'API (URS-F-03.1) | `POST /api/tickets` avec `low`, `normal`, `high` | 201 à chaque fois ; `dueAt - createdAt` = 259200 s, 86400 s, 28800 s | `F0SlaFoundationTest::testAc9UrsF0301DueTimePerPriority` : rejoué, passe | Oui |
| F0-AC10 | Échéance par priorité dans le constructeur (URS-F-03.1) | `new Ticket('t', 'd', 'high', 2026-01-01T00:00:00+00:00)` ; `Ticket::slaTargetHours()` | `getDueAt()` = `2026-01-01T08:00:00+00:00` ; 72 / 24 / 8 pour `low` / `normal` / `high` | `F0SlaFoundationTest::testAc10UrsF0301ConstructorDueAtAndSlaTargetHours` : rejoué, passe | Oui |
| F0-AC11 | Priorité inconnue (URS-F-03.2) | `POST /api/tickets` avec `urgent` ; `slaTargetHours('urgent')`, `('')`, `('LOW')` | 201 avec `dueAt - createdAt` = 24 h ; 24 dans les trois cas | `F0SlaFoundationTest::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget` : rejoué, passe | Oui |
| F0-AC12 | Échéance stockée une fois (URS-F-03.3) | `createTicket(priority: 'high', createdAt: $t0, dueAt: $t0 + 1 h)`, `flush()`, `clear()`, rechargement ; réflexion sur `setDueAt` | `getDueAt()` = `$t0 + 1 h` (et non `$t0 + 8 h`) ; aucun `setDueAt()` public | `F0SlaFoundationTest::testAc12UrsF0303DueAtStoredOnceNeverRecomputed` : rejoué, passe (inclut l'assertion `method_exists` / `isPublic()` sur `setDueAt`) | Oui |
| F0-AC13 | Format ISO 8601 de `dueAt` (URS-F-03.4) | `POST /api/tickets` puis `GET /api/tickets/{id}` | `dueAt` conforme à `^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$` et identique dans les deux réponses | `F0SlaFoundationTest::testAc13UrsF0304DueAtIsIso8601AndStableAcrossResponses` : rejoué, passe | Oui |
| F0-AC14 | Jeu de clés exact (URS-N-03) | `POST /api/tickets` (201), `GET /api/tickets` (200), `GET /api/tickets/{id}` (200), `PATCH /api/tickets/{id}/status` `{"status": "closed"}` (200), `GET /api/stats` | Clés exactement `id, title, description, priority, status, createdAt, dueAt, assignee` ; stats exactement `total, open, in_progress, closed, closed_ratio` | `F0SlaFoundationTest::testAc14UrsN03TicketKeySetIsExactOnEveryEndpoint` : rejoué, passe | Oui |
| F0-AC15 | Liste vide (URS-N-03) | `GET /api/tickets` sans ticket | 200 `{"items": [], "count": 0}` | `F0SlaFoundationTest::testAc15UrsN03EmptyListStillReturnsItemsAndCount` : rejoué, passe | Oui |

Bilan : 15 critères, 15 couverts par un test rejoué et passant ; 2 clauses (AC3, AC5 :
« assertions inchangées ») complétées par revue de code du diff ; 0 critère en échec ;
0 critère sans test.

## 6. Tests End-to-End

Rejeu de `tools/php bin/console app:simulate-traffic --reset` le 14 octobre 2026 sur
`291c830`.

**Scénario** : réinitialisation du schéma de `var/data_dev.db` (par `SchemaTool`, table
`ticket` vide), puis douze requêtes HTTP dans l'ordre : `GET /api/stats` (table vide),
`POST /api/tickets` x2, `POST /api/tickets` sans `priority`, `POST /api/tickets`,
`GET /api/tickets`, `GET /api/tickets/1`, `GET /api/tickets/9999`,
`PATCH /api/tickets/1/status` x2 (valide, puis invalide), `GET /api/tickets?status=open`,
`GET /api/stats`.

**Codes HTTP obtenus** :

| Code | Nombre |
|------|--------|
| 200 | 5 |
| 201 | 3 |
| 422 | 1 |
| 500 | 3 |

**Journal** : 67 lignes ajoutées à `var/log/dev.log`, dont **3 lignes `CRITICAL`** :

1. `request.CRITICAL: Uncaught PHP Exception DivisionByZeroError: "Division by zero" at TicketStats.php line 29` (`GET /api/stats` sur table vide) ;
2. `request.CRITICAL: Uncaught PHP Exception ErrorException: "Warning: Undefined array key "priority"" at TicketController.php line 40` (`POST /api/tickets` sans `priority`) ;
3. `request.CRITICAL: Uncaught PHP Exception Error: "Call to a member function toArray() on null" at TicketController.php line 54` (`GET /api/tickets/9999`).

Ces trois `CRITICAL` sont identiques, ligne pour ligne, à ceux relevés par le
vérificateur au tour 2 (`remaining_criticals_out_of_scope`) et figurent déjà dans le
périmètre « hors item » de la spécification F0. Aucun `CRITICAL` nouveau n'apparaît.

**Ce que ce rejeu prouve pour F0** : les trois `POST /api/tickets` complets renvoient
201 alors que le simulateur envoie la priorité `urgent` (le calcul de `dueAt` dans le
constructeur ne lève pas d'erreur sur une priorité inconnue, URS-F-03.2) ; `GET
/api/tickets`, `GET /api/tickets/1`, `GET /api/tickets?status=open` et `PATCH
/api/tickets/1/status` renvoient 200 avec le schéma migré ; `GET /api/stats` renvoie 200
une fois des tickets présents ; la sérialisation des nouvelles colonnes ne provoque
aucune régression de code HTTP.

**Ce qu'il ne prouve pas** : le contenu des réponses (présence et format de `dueAt` et
`assignee`), la migration (le `--reset` recrée le schéma par `SchemaTool`, pas par
`doctrine:migrations:migrate`), et l'absence de régression sur les trois chemins déjà en
erreur 500 avant cette version.

## 7. Traçabilité / Logs

**Plage de commits** : `ce6a1a4..291c830` (premier commit de la plage `0c82835`, dernier
`291c830`). Commit de code de l'item F0 : `c63c5ff`. Commit rejoué : `291c830`
(`291c830f330d2d6e16a0a70b204f01a7ef81dded`, 2026-10-14 15:56:01 +0200), arbre de
travail propre. Diff applicatif de la plage (`git diff --stat ce6a1a4..HEAD -- src
migrations tests`) : 8 fichiers, `migrations/Version20261014133137.php`,
`src/Entity/Agent.php`, `src/Entity/Ticket.php`, `src/Repository/AgentRepository.php`,
`tests/Api/ApiTestCase.php`, `tests/Api/F0SlaFoundationTest.php`,
`tests/Api/TicketApiTest.php`, `tests/Unit/F0SlaFoundationMigrationTest.php`.

**Suite PHPUnit** (14 octobre 2026, `291c830`) :

```
tools/php vendor/bin/phpunit
PHPUnit 13.4.2 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.5
..................                                                18 / 18 (100%)
Time: 00:01.822, Memory: 36.00 MB
OK (18 tests, 160 assertions)
```

Répartition : `TicketApiTest` 3 tests, `F0SlaFoundationTest` 13 tests,
`F0SlaFoundationMigrationTest` 2 tests. Aucun test en échec, aucun test ignoré.

**Trafic simulé** (14 octobre 2026, `291c830`) :

```
tools/php bin/console app:simulate-traffic --reset
Schema reset: ticket table is empty.
HTTP 200: 5
HTTP 201: 3
HTTP 422: 1
HTTP 500: 3
Logs written to var/log/dev.log
```

Lignes ajoutées à `var/log/dev.log` : 67 (de la ligne 1246 à la ligne 1312), dont 3
`CRITICAL` (voir section 6).

**Migration sur base vide** (14 octobre 2026, `291c830`, fichier SQLite neuf via
`DATABASE_URL`) :

```
tools/php bin/console doctrine:migrations:migrate -n
[notice] Migrating up to DoctrineMigrations\Version20261014133137
[notice] finished in 11.7ms, used 16M memory, 2 migrations executed, 9 sql queries
[OK] Successfully migrated to version: DoctrineMigrations\Version20261014133137
```

Schéma obtenu (`sqlite_master`) :

- `agent (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, active BOOLEAN NOT NULL)` ;
- `UNIQ_268B9C9DE7927C74` : `CREATE UNIQUE INDEX ... ON agent (email)` ;
- `ticket (..., created_at DATETIME NOT NULL, due_at DATETIME DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, CONSTRAINT FK_97A0ADA359EC7D60 FOREIGN KEY (assignee_id) REFERENCES agent (id) ON DELETE SET NULL ...)` ;
- `IDX_97A0ADA359EC7D60` : `CREATE INDEX ... ON ticket (assignee_id)`.

Le schéma correspond au point 3 « Migration » de la spécification.

**Tours du vérificateur (item F0)** :

| Tour | Verdict | Suite | Motifs |
|------|---------|-------|--------|
| 1 (`verdict-1.json`) | `FAIL` | `FAIL (18 tests, 159 assertions, 1 failure: F0SlaFoundationTest::testAc5ApiTestCaseShapeAndTicketApiTestExtendsIt)` | AC5 : `tests/Api/TicketApiTest.php` n'avait pas été déplacé sur `ApiTestCase` (héritait encore de `WebTestCase` avec ses propres `setUp()`, `postJson()`, `body()`). AC1 / AC3 : `testAc1MigrateKeepsExistingDevRows` lisait le fichier vivant `var/data_dev.db`, réécrit par `app:simulate-traffic --reset`, donc non fiable après le rejeu de trafic. |
| 2 (`verdict-2.json`) | `PASS` | `OK (18 tests, 160 assertions)` | Les deux points corrigés : `TicketApiTest` hérite d'`ApiTestCase` ; le test de migration construit sa propre base dans un fichier temporaire. Couverture AC1 à AC15 confirmée test par test. Trois `CRITICAL` du trafic, tous antérieurs et hors périmètre. |

Le rapport du testeur (`tester.md`) confirme : `RUN RESULT: tools/php vendor/bin/phpunit:
OK (18 tests, 160 assertions)`, critères couverts AC1, AC2, AC4 à AC15 ; AC3 déclaré « non
couvert » par un test dédié car il est la suite existante elle-même.

## 8. Anomalies détectées

| ID | Description | Gravité | Statut / Action |
|----|-------------|---------|-----------------|
| A1 | **Antérieur à cette version.** `GET /api/stats` sur une table `ticket` vide renvoie 500 (`DivisionByZeroError`, `src/Service/TicketStats.php:29`). Reproduit au rejeu du trafic. Hors périmètre de F0 selon la spécification. | Majeure | Ouverte ; à traiter par un item de maintenance (`/improve`). Non bloquante pour 0.2.0 : pas de régression, la ligne `CRITICAL` est identique à celle de la version 0.1.0. |
| A2 | **Antérieur à cette version.** `POST /api/tickets` sans clé `priority` renvoie 500 (`Undefined array key "priority"`, `src/Controller/TicketController.php:40`) au lieu d'une erreur 4xx. Reproduit au rejeu du trafic. Hors périmètre de F0. | Majeure | Ouverte ; à traiter par un item de maintenance. Non bloquante pour 0.2.0 (pas de régression). |
| A3 | **Antérieur à cette version.** `GET /api/tickets/9999` (identifiant inconnu) renvoie 500 (`toArray() on null`, `src/Controller/TicketController.php:54`) au lieu de 404. Reproduit au rejeu du trafic. Hors périmètre de F0. | Majeure | Ouverte ; à traiter par un item de maintenance. Non bloquante pour 0.2.0 (pas de régression). |
| A4 | Preuve AC1 instable au tour 1 : le test de migration lisait le fichier vivant `var/data_dev.db`, réécrit par `app:simulate-traffic --reset`. Corrigé avant commit (fixture reconstruite dans un fichier SQLite temporaire). Conséquence résiduelle : la migration sur le fichier `var/data_dev.db` d'origine, avec ses trois lignes réelles, n'est pas rejouée par ce dossier. | Mineure | Corrigée au tour 2. Le rejeu manuel sur base vide (section 7) et les deux tests AC1 couvrent le comportement ; aucune action supplémentaire exigée. |
| A5 | AC2 demande un `KernelTestCase` ; le test `testAc2EntitiesRoundTripThroughPersistence` s'exécute sur `ApiTestCase` (un `WebTestCase`, sur-ensemble de `KernelTestCase`). Le scénario et les assertions sont ceux du critère. | Mineure | Acceptée par le vérificateur (tour 1, `notes`). Aucune action. |
| A6 | Les clauses « assertions inchangées » d'AC3 et AC5 ne sont couvertes par aucun test ; elles reposent sur la revue de code du diff de `tests/Api/TicketApiTest.php` (`+1 -32`, corps des trois tests intacts). | Mineure | Documentée ; revue effectuée par l'enregistreur. Aucune action. |
| A7 | Remarque de style du vérificateur (tour 2, `notes`) : `src/Entity/Agent.php` pourrait utiliser la promotion de propriétés dans le constructeur (convention `AGENTS.md`). Cosmétique, sans effet fonctionnel. | Mineure | À reprendre dans un item ultérieur du module SLA (U1) ; non bloquante. |

Synthèse : 7 anomalies, 0 bloquante, 3 majeures (toutes antérieures à cette version), 4
mineures.

## 9. Conclusion

**La version 0.2.0 est validée au commit `291c830`.**

Les quinze critères d'acceptation de l'item F0 sont chacun couverts par au moins un test
rejoué par l'enregistreur le 14 octobre 2026, et la suite complète est verte
(`OK (18 tests, 160 assertions)`). Les deux migrations s'appliquent sans erreur sur une
base vide et produisent le schéma spécifié. Le rejeu du trafic simulé ne révèle aucune
régression de code HTTP ni aucun `CRITICAL` nouveau : les trois `CRITICAL` observés sont
strictement ceux de la version 0.1.0 et restent hors du périmètre de l'item. Les deux
tours du vérificateur (FAIL puis PASS) sont cohérents avec le rejeu.

Points d'attention avant la validation finale et pour les versions suivantes :

1. Traiter les trois anomalies majeures antérieures A1, A2 et A3 (erreurs 500 sur
   `GET /api/stats` à vide, `POST /api/tickets` sans `priority`, `GET /api/tickets/9999`)
   par le cycle de maintenance ; elles ne relèvent pas de F0 mais restent présentes en
   production.
2. Conserver la stratégie de fixture temporaire du test de migration (A4) pour les
   migrations suivantes du module SLA, afin que la preuve AC1 ne dépende jamais du
   fichier `var/data_dev.db`.
3. Lors de l'item U1, reprendre la remarque de style A7 sur `Agent` et ajouter la
   validation d'unicité de l'email côté API (422), dont seule la contrainte de stockage
   est prouvée ici (AC7).
4. Exercer `ON DELETE SET NULL` par un test dès qu'un item autorise la suppression d'un
   agent ; la clause est présente dans le schéma mais son effet n'est pas vérifié.

Ce dossier est un projet complémentaire au rapport de validation officiel ; il ne s'y
substitue pas et ne vaut pas, à lui seul, autorisation de mise en production.
