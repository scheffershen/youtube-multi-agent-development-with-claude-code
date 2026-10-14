---
sidebar_position: 0
id: dossier-preuves-validation-0-4-0
title: Version 0.4.0
---

# Dossier de Preuves de Validation Fonctionnelle - Version 0.4.0

Date : 14 octobre 2026. Version précédente : 0.3.0. Commit de référence : `f05a245`
(branche `build/urs-002`, plage `36d2712..f05a245`).

## 1. Contexte

La version 0.4.0 traite la dernière demande humaine restée ouverte après 0.3.0 et
complète l'outillage du dépôt sans autre changement d'API. Depuis `v0.3.0`, elle
apporte :

- **R2 - Priorité `urgent` avec une cible SLA de 2 heures**
  (`specs/R2-urgent-priority.md`, commit `1eac315`, demande humaine
  `requests/002-urgent-priority.md`, URS-F-03.1, URS-F-03.2, URS-F-03.3, URS-F-05.1,
  URS-F-05.2, contraintes URS-N-02 et URS-N-03 ; décisions humaines D1, D2, D3, voir
  section 7). `Ticket::PRIORITIES` devient `['low', 'normal', 'high', 'urgent']`
  (`urgent` ajouté après `high`, décision D2) et `Ticket::SLA_TARGET_HOURS` gagne
  `'urgent' => 2` (décision D1). `POST /api/tickets` avec `"priority": "urgent"` renvoie
  201 avec `dueAt` égal à `createdAt` + 2 h ; toute autre valeur hors des quatre reste
  refusée en 422 ; `GET /api/reports/sla` liste une quatrième ligne `urgent`, présente
  même à zéro, avant l'éventuelle ligne `other`. Les tickets `urgent` enregistrés avant
  cette version conservent leur `dueAt` tel quel (`null` ou + 24 h) ; aucune migration.
  Le scénario standard du simulateur passe de `201: 2, 422: 3` à `201: 3, 422: 2`. Cet
  item renverse la décision FX D1 de 0.3.0 (`urgent` refusé) et clôt la demande 002.
- **Protocole de décision** (commit `16e098a`, `CLAUDE.md`, les dix rôles de
  `.claude/agents/`, les trois skills, `.claude/workflows/improve.js`,
  `approvals/README.md`) : les agents remettent tout choix matériel à une personne
  (bloc `DECISION NEEDED`), les réponses sont consignées dans
  `reports/items/<item>/decisions.md` et `approvals/approved/decision-<item>-D<n>.md`.
  Dans la même plage, deux décisions prises sans personne en 0.3.0 sont **ratifiées
  après coup** par Yi Shen (`approvals/approved/ratify-U1-D1.md`,
  `approvals/approved/ratify-FX-D1.md`, commit `c1103c7`). Aucun impact sur le code.
- **Conteneur Docker** (commits `e6947a1`, `826a9a0` : `Dockerfile`, `compose.yaml`,
  `docker/entrypoint.sh`, `.dockerignore`) : image `php:8.4-cli-alpine`, service `app`
  sur le port 8000, modes `serve` (migration puis serveur PHP intégré) et `test` (suite
  PHPUnit avec l'environnement d'exécution neutralisé). Infrastructure sans item, sans
  spécification ni verdict de vérificateur, sans changement d'API ; voir sections 4 et 7
  pour ce qui en est prouvé.
- **Corrections de revue de l'outillage des agents** (mêmes commits que le protocole,
  `.claude/`) : sans impact sur le code applicatif ; non vérifiées par ce dossier.

Autres commits de la plage, sans item ni verdict : les commits `gate:` (`bf3f719`,
`3ac6b50`, `c2a4ce3`, `57b3325`, `d684e49`, `c1103c7`, fichiers `approvals/`),
`2597a9f` (rapport de build), `052eaa4` (pages `docs/user/tickets.md` et
`docs/user/statistiques.md`, fragment `release/unreleased/R2.md`, dossier
`reports/items/R2/`) et `f05a245` (changelog 0.4.0). Aucun de ces commits ne touche
`src/`.

La plage ne contient **aucune migration** : `migrations/` reste limité à
`Version20261014130059` et `Version20261014133137` (0.2.0) ; aucun changement de schéma
(`git diff --name-only 36d2712..HEAD -- migrations` vide). Le diff applicatif se limite
à `src/Entity/Ticket.php` (`+2 -2`).

## 2. Avertissement

Ce dossier est **complémentaire** au rapport de validation officiel ; il ne s'y
substitue pas. Les preuves qu'il contient ont été produites par des agents automatisés
(rédacteur de spécification, développeur, testeur, vérificateur) puis **rejouées** par
l'agent enregistreur de validation sur le commit de référence, à la date indiquée.

Seuls les résultats marqués « rejoué » dans ce document correspondent à une exécution
réelle effectuée par l'enregistreur. Une mention « revue de code » signifie que
l'enregistreur a lu le code ou le diff correspondant, **sans** qu'un test l'exécute :
une revue de code n'est pas une exécution de test et ne constitue pas une preuve de
même niveau. Une mention « rapporté » désigne un résultat consigné par un autre agent
et non rejoué par l'enregistreur. La présence d'un test dans le dépôt n'est jamais
considérée comme la preuve qu'il passe.

## 3. Objectif de vérification

Vérifier que la version 0.4.0 :

**Item R2 - Priorité `urgent`** (URS-F-03.1, URS-F-03.2, URS-F-03.3, URS-F-05.1,
URS-F-05.2, URS-N-02, URS-N-03 ; décisions D1, D2, D3)

- AC1 (URS-F-03.1, D1) : `POST /api/tickets` avec `priority` `urgent` renvoie 201 JSON
  avec exactement les huit clés ticket, `priority === 'urgent'`, `status === 'open'`,
  `assignee === null` et `dueAt - createdAt` = 7200 s exactement.
- AC2 (D1, D2) : `Ticket::slaTargetHours('urgent') === 2`,
  `Ticket::SLA_TARGET_HOURS['urgent'] === 2`,
  `Ticket::PRIORITIES === ['low', 'normal', 'high', 'urgent']` ; `low`, `normal`,
  `high` restent à 72, 24, 8.
- AC3 (URS-F-03.2) : `Ticket::slaTargetHours()` renvoie 24 pour `''`, `'LOW'`,
  `'Urgent'`, `'URGENT'`, `'critical'` ; `Ticket::SLA_DEFAULT_PRIORITY === 'normal'`.
- AC4 (URS-N-02) : `Urgent`, `URGENT`, `High`, `critical`, `""`, `null`, `123` et la clé
  absente renvoient 422 JSON `{"errors": {"priority": "<non vide>"}}` ; rien n'est
  enregistré (`count === 0`).
- AC5 (URS-F-05.2, D2) : sur table vide, `GET /api/reports/sla` renvoie 200, clés
  `generatedAt, priorities`, quatre lignes `['low', 'normal', 'high', 'urgent']`, sans
  `other` ; la ligne `urgent` est exactement
  `{"priority": "urgent", "total": 0, "breached": 0, "breach_rate": 0.0}`.
- AC6 (URS-F-05.1, D2) : trois `urgent` (un dépassé ouvert, un à venir, un clos échu) et
  un `escalated` dépassé donnent les lignes `['low', 'normal', 'high', 'urgent', 'other']`,
  `urgent` 3/1/33.3, `low`, `normal`, `high` à zéro, `other` exactement 1/1/100.0 ;
  `?breached=true` renvoie `count === 2`.
- AC7 (URS-N-03) : après AC1, `GET /api/tickets` (`count === 1`, `items[0].priority`
  `urgent`), `GET /{id}`, `PATCH /{id}/status` `in_progress`, `PATCH /{id}/assignee`
  `null` renvoient 200 avec les huit clés et `priority === 'urgent'` ;
  `?status=in_progress` `count === 1` ; `GET /api/stats` cinq clés, `total === 1`.
- AC8 (URS-F-03.3) : un ticket `urgent` créé avec `dueAt` = `createdAt` + 24 h (forme
  F0-FX) et une ligne `urgent` insérée avec `due_at` NULL (forme migrée F0) conservent
  ces valeurs après rechargement (ni l'une ni l'autre n'égale + 2 h) ; le rapport donne
  `urgent` 2/1 sans `other` ; `GET /api/tickets` renvoie les deux avec `priority`
  `urgent`.
- AC9 (section 6, URS-N-02) : les douze requêtes du scénario standard, rejouées par le
  client de test, donnent exactement `[200, 201, 201, 422, 201, 200, 200, 404, 200, 422, 200, 200]`,
  toutes en JSON, la cinquième avec `priority === 'urgent'` ;
  `app:simulate-traffic --reset` via `CommandTester` sort en code 0, affiche
  `HTTP 201: 3` et `HTTP 422: 2` et jamais `-> 5xx`.
- AC10 (URS-N-03, D3) : les tests livrés listés par le comportement requis 8 (FX AC2 et
  assertion `PRIORITIES`, FX `EXPECTED_STATUSES`, FX AC11, F0 AC11, U5 AC6 et
  `KNOWN_PRIORITIES`) et, par décision D3, U5 AC1, AC2, AC4, AC10 sont amendés et rien
  d'autre ; `tests/Api/TicketApiTest.php` est octet pour octet identique ; la suite
  complète passe.

**Infrastructure (sans critère d'acceptation)** : la suite PHPUnit passe aussi dans le
conteneur (`docker compose run --rm app test`).

### Périmètre non couvert

- **Aucun parcours navigateur** : TicketFlow est une API JSON ; aucune interface graphique
  n'est exercée.
- **Aucun déploiement réel ni service externe** : toutes les preuves sont produites en
  local sur SQLite (`var/data_dev.db` pour le trafic, `var/data_test_<pid>.db` pour la
  suite, hôte comme conteneur).
- **Trafic simulé limité aux points d'entrée de 0.1.0** : le scénario standard de
  `app:simulate-traffic` n'appelle toujours ni `/api/agents`, ni
  `PATCH /api/tickets/{id}/assignee`, ni `?breached=`, ni `/api/reports/sla`. La ligne
  `urgent` du rapport SLA (R2 AC5, AC6, AC8) n'est donc prouvée que par PHPUnit, jamais
  sous trafic (voir A4). Le simulateur ne vérifie que les codes HTTP : le contenu des
  réponses (`dueAt` à + 2 h, corps d'erreur) n'est prouvé que par PHPUnit.
- **Conteneur Docker** : seul le mode `test` de `docker/entrypoint.sh` est rejoué par
  l'enregistreur. Le mode `serve` (migration au démarrage, serveur `php -S` sur
  `0.0.0.0:8000`, `healthcheck`) n'est pas exercé : au moment du rejeu un conteneur
  `ticketflow-app-1` lancé par ailleurs était observé `Up (healthy)` par
  `docker compose ps`, ce qui est une observation, pas une preuve. Le trafic simulé n'a
  pas été rejoué dans le conteneur. La version de PHP diffère entre l'hôte (8.4.5) et
  l'image (8.4.26) ; la suite passe dans les deux (voir A5).
- **Protocole de décision et corrections de l'outillage des agents** : changements de
  `.claude/`, `CLAUDE.md`, `approvals/` sans test ni verdict ; seul leur produit (les
  fichiers de décision et de ratification) est lu et listé en section 7.
- **Documentation utilisateur** (`docs/user/tickets.md`, `docs/user/statistiques.md`,
  changelog, flash-info) : non vérifiée par ce dossier.
- **Tickets `urgent` antérieurs** : la cible de 2 h ne s'applique qu'aux tickets créés à
  partir de cette version ; les lignes `urgent` existantes gardent `dueAt` `null` ou
  + 24 h (R2 AC8, URS-F-03.3). Leur recalcul n'est ni demandé ni exercé (voir A3).
- **Hors spécification, délibérément** : pas de modification de priorité d'un ticket
  existant, pas de changement de `STATUSES`, des points d'entrée agents, du filtre
  `breached`, de `SLA_DEFAULT_PRIORITY` ni des cibles `low`/`normal`/`high` ; pas de
  `GET /api/agents/{id}`, de suppression d'agent, de pagination ; `ON DELETE SET NULL`
  toujours non exercé (point ouvert depuis 0.2.0).

## 4. Méthodologie

1. **Spécification avant code.** L'item R2 dispose d'une spécification
   `specs/R2-urgent-priority.md` rédigée par l'agent spec-writer à partir de la demande
   humaine 002 et de l'URS-002 (sections 4 et 5), avec **10 critères d'acceptation**
   numérotés et leurs identifiants URS, avant toute ligne de code. Deux décisions
   (D1 cible SLA, D2 position de `urgent`) ont été remises à une personne avant la
   rédaction finale ; une troisième (D3, amendement de quatre tests U5 supplémentaires)
   a été remise pendant le développement par le développeur et le testeur
   indépendamment. Les trois sont tranchées par Yi Shen le 14 octobre 2026 (section 7).
2. **Tests écrits depuis la spécification.** L'agent testeur a écrit
   `tests/Api/R2UrgentPriorityTest.php` (9 méthodes, AC1 à AC9) héritée de `ApiTestCase`
   à partir de la seule spécification, sans accès au code produit par l'agent
   développeur, qui travaillait en parallèle. Chaque méthode est nommée `testAc<n>...`
   et porte l'identifiant URS (`UrsF0301`, `UrsN02`, ...). AC10 n'a pas de méthode
   propre : il consiste en l'amendement par le testeur des tests livrés (FX, F0, U5),
   chaque amendement portant une ligne de docblock citant R2 et la décision, et dans le
   passage de la suite complète. Le développeur n'a pas touché `tests/`.
3. **Vérification indépendante.** L'agent vérificateur a confronté le code et les tests à
   la spécification. L'item a été validé en **un seul tour** (`verdict-1.json`, `PASS`,
   détail en section 7) ; aucun tour `FAIL` n'a été nécessaire. Le commit `1eac315` n'a
   été créé qu'après le `PASS`.
4. **Rejeu par l'enregistreur.** Le présent dossier repose sur un rejeu effectué le
   14 octobre 2026 (21:48, heure locale UTC+2) sur le commit `f05a245` (HEAD de
   `build/urs-002`) :
   - `tools/php vendor/bin/phpunit` (suite complète, un seul processus, base
     `var/data_test_<pid>.db` dédiée) ;
   - `tools/php bin/console app:simulate-traffic --reset` (codes HTTP et lignes ajoutées
     à `var/log/dev.log`) ;
   - `docker compose run --rm app test` (suite complète dans le conteneur, image
     `ticketflow:dev` construite depuis le `Dockerfile` de la plage), Docker étant
     disponible sur le poste de rejeu ; ce rejeu complète, sans s'y substituer, le
     résultat rapporté par l'orchestrateur ;
   - pas de rejeu de migration : la plage ne contient aucune migration
     (`git diff --name-only 36d2712..HEAD -- migrations` vide).
   Au moment du rejeu, `src/`, `tests/`, `migrations/`, `config/`, `composer.json` et
   `composer.lock` étaient identiques au commit ; seuls deux fichiers de
   `docs/user/annexe/flash-info/` étaient modifiés ou non suivis (rédaction concurrente
   du flash-info 0.4.0), sans effet sur l'exécution.
5. **Lecture des preuves uniquement.** L'enregistreur a lu le verdict
   (`reports/items/R2/verdict-1.json`), le rapport du testeur (`tester.md`), `item.json`,
   la spécification, les fichiers de test, les fichiers `decisions.md` des items R2, U1
   et FX et les fichiers `approvals/approved/decision-R2-D*.md` et `ratify-*.md`. Il n'a
   lu ni le rapport du développeur, ni les fragments de changelog, ni le flash-info, ni
   les messages de commit (la liste des commits ne sert qu'à délimiter la plage).
   Lorsqu'une clause n'est couverte par aucun test (AC10, clauses « rien d'autre dans
   les tests existants » et « `TicketApiTest` octet pour octet identique » ; comportement
   requis 6, « aucune migration » ; indication « deux constantes seulement »), elle est
   marquée « revue de code » et porte sur la lecture effective de
   `git diff --numstat 36d2712..HEAD -- src tests` et de
   `git diff 36d2712..HEAD -- src/Entity/Ticket.php` (section 7).

L'existence d'un test dans le dépôt n'est pas traitée comme une preuve de passage ; seul
le résultat du rejeu de l'étape 4 fait foi.

## 5. Tests utilisateurs

Statut rejoué le 14 octobre 2026 sur `f05a245` : `OK (126 tests, 1302 assertions)`
(hôte) et `OK (126 tests, 1302 assertions)` (conteneur). Toutes les méthodes citées
ci-dessous figurent dans la suite rejouée avec le statut « passe » ; aucune n'est en
échec, ignorée, incomplète ou marquée risquée.

**Item R2** (`tests/Api/R2UrgentPriorityTest.php`, 9 tests, plus les amendements de
`FXUrsNAcceptanceSweepTest`, `F0SlaFoundationTest` et `U5SlaReportTest`)

| ID | Fonctionnalité | Scénario | Résultat attendu | Résultat obtenu | Conforme |
|----|----------------|----------|------------------|-----------------|----------|
| R2-AC1 (URS-F-03.1, D1) | Création `urgent` | `POST /api/tickets` `{"title": "Payment double charged", "description": "Customer #4411 charged twice.", "priority": "urgent"}` | 201, `Content-Type` JSON, huit clés exactement, `priority urgent`, `status open`, `assignee null`, `dueAt - createdAt` = 7200 s | `testAc1UrsF0301UrgentIsAcceptedWithTwoHourDueAt` : rejoué, passe | Oui |
| R2-AC2 (D1, D2) | Constantes de l'entité | `Ticket::slaTargetHours('urgent')`, `SLA_TARGET_HOURS`, `PRIORITIES` | `2` ; `['urgent'] === 2` ; `['low', 'normal', 'high', 'urgent']` ; 72, 24, 8 inchangés | `testAc2D1D2SlaTargetHoursAndPrioritiesConstants` : rejoué, passe | Oui |
| R2-AC3 (URS-F-03.2) | Repli entité conservé | `slaTargetHours()` sur `''`, `'LOW'`, `'Urgent'`, `'URGENT'`, `'critical'` ; `SLA_DEFAULT_PRIORITY` | 24 dans les cinq cas ; `'normal'` | `testAc3UrsF0302UnknownStringsStillFallBackToNormalTarget` : rejoué, passe | Oui |
| R2-AC4 (URS-N-02) | Rejet sensible à la casse | `Urgent`, `URGENT`, `High`, `critical`, `""`, `null`, `123`, clé absente | 422 JSON à chaque fois, clés `['errors']` puis `['priority']`, message non vide ; `GET /api/tickets` `count === 0` | `testAc4UrsN02EverythingElseIsRejectedCaseSensitively` : rejoué, passe | Oui |
| R2-AC5 (URS-F-05.2, D2) | Rapport à vide | `GET /api/reports/sla` sans ticket | 200, clés `generatedAt, priorities`, 4 lignes `['low', 'normal', 'high', 'urgent']`, pas d'`other`, `priorities[3]` exactement `urgent` 0/0/0.0 | `testAc5UrsF0502EmptyReportListsUrgentRowFourthWithZeros` : rejoué, passe | Oui |
| R2-AC6 (URS-F-05.1, D2) | Calcul de la ligne `urgent` | 3 `urgent` (dépassé ouvert, à venir, clos échu) + 1 `escalated` dépassé | Lignes `['low', 'normal', 'high', 'urgent', 'other']` ; `urgent` 3/1/33.3 ; `low`, `normal`, `high` à zéro ; `other` exactement 1/1/100.0 ; `?breached=true` `count === 2` | `testAc6UrsF0501UrgentRowIsComputedAndOtherStaysTrailing` : rejoué, passe | Oui |
| R2-AC7 (URS-N-03) | Forme des points d'entrée | Après AC1 : liste, `GET /{id}`, `PATCH /{id}/status` `in_progress`, `PATCH /{id}/assignee` `null`, `?status=in_progress`, `GET /api/stats` | `count === 1`, `items[0].priority urgent` ; 200 huit clés et `priority urgent` x3 ; `count === 1` ; cinq clés stats, `total === 1` | `testAc7UrsN03EndpointsKeepTheirShapeForUrgentTicket` : rejoué, passe | Oui |
| R2-AC8 (URS-F-03.3) | Lignes `urgent` antérieures | `createTicket(urgent, createdAt T0, dueAt T0 + 24 h)` ; `INSERT` brut `urgent` avec `due_at` NULL ; `flush()`/`clear()` | `find()` : `dueAt` T0 + 24 h et `null`, jamais T0 + 2 h ; rapport `urgent` 2/1, pas d'`other` ; liste : deux tickets `priority urgent` | `testAc8UrsF0303ExistingUrgentRowsKeepTheirStoredDueAt` : rejoué, passe | Oui |
| R2-AC9 (section 6, URS-N-02) | Scénario standard | Douze requêtes via le client de test ; `app:simulate-traffic --reset` via `CommandTester` | `[200, 201, 201, 422, 201, 200, 200, 404, 200, 422, 200, 200]`, JSON partout, 5e réponse `priority urgent` ; code 0, `HTTP 201: 3`, `HTTP 422: 2`, aucun `-> 5xx` | `testAc9Section6UrsN02ScenarioReplayAndConsoleCounts` : rejoué, passe. Séquence identique au rejeu de trafic en environnement `dev` (section 6) | Oui |
| R2-AC10 (URS-N-03, D3) | Amendement des tests livrés | FX AC2 (`urgent` retiré de la liste refusée, `PRIORITIES` à quatre valeurs), FX `EXPECTED_STATUSES` (5e code 201), FX AC11 (`422: 2`, `201: 3`), F0 AC11 (`urgent` 201 et + 7200 s, `''`/`'LOW'` à 24), U5 AC6 (`escalated`, `KNOWN_PRIORITIES` à quatre valeurs, cinq lignes) ; par D3 : U5 AC1, AC2, AC4, AC10 (quatre lignes) ; rien d'autre ; `TicketApiTest` identique ; suite complète verte | Neuf méthodes amendées passantes ; aucun autre fichier de test préexistant modifié ; diff de `TicketApiTest` vide ; `OK (126 tests, 1302 assertions)` | `FXUrsNAcceptanceSweepTest::testAc2UrsN02UnknownPriorityReturns422AndPriorityListIsExact`, `::testAc10Section6StandardScenarioReplayProducesNo500`, `::testAc11Section6ConsoleCommandReportsNo500`, `F0SlaFoundationTest::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget`, `U5SlaReportTest::testAc6UnknownPrioritiesAreAggregatedUnderTrailingOtherEntry`, `::testAc1UrsF0503EmptyTableReturns200WithFourZeroEntries`, `::testAc2UrsF0501TotalsBreachedAndRatesAreComputedPerPriority`, `::testAc4UrsF0502KnownPriorityWithNoTicketIsListedWithZeroRateAndNoOtherEntry`, `::testAc10ServiceAndRepositoryCountThroughSharedClockPinnedPredicate` : rejoués, passent ; les trois tests de `TicketApiTest` rejoués, passent ; suite complète rejouée `OK (126 tests, 1302 assertions)`. Clauses « rien d'autre » et « fichier identique » : revue de code de `git diff --numstat 36d2712..HEAD -- tests` (seuls `F0SlaFoundationTest.php` `+6 -6`, `FXUrsNAcceptanceSweepTest.php` `+12 -9`, `U5SlaReportTest.php` `+16 -11` et le nouveau `R2UrgentPriorityTest.php` `+370` ; `TicketApiTest.php`, `ApiTestCase.php`, `bootstrap.php`, `Unit/` : diff vide) | Oui (tests rejoués ; clauses d'exclusivité et d'intégrité par revue de code) |

Bilan : 10 critères, 10 couverts par au moins un test rejoué et passant ; 2 clauses de
l'AC10 (« rien d'autre », « fichier identique ») complétées par revue de code du diff ;
0 critère en échec ; 0 critère sans test.

## 6. Tests End-to-End

Rejeu de `tools/php bin/console app:simulate-traffic --reset` le 14 octobre 2026 à
21:48 (UTC+2) sur `f05a245`.

**Scénario** : réinitialisation du schéma de `var/data_dev.db` (par `SchemaTool`, table
`ticket` vide), puis douze requêtes HTTP dans l'ordre (fixture de
`FXUrsNAcceptanceSweepTest::SCENARIO` et de `R2UrgentPriorityTest::SCENARIO`, identique
à celle de la commande) : `GET /api/stats` (table vide), `POST /api/tickets` `high`,
`POST /api/tickets` `low`, `POST /api/tickets` sans `priority`, `POST /api/tickets`
`urgent`, `GET /api/tickets`, `GET /api/tickets/1`, `GET /api/tickets/9999`,
`PATCH /api/tickets/1/status` `in_progress`, `PATCH /api/tickets/1/status` `done`,
`GET /api/tickets?status=open`, `GET /api/stats`.

**Codes HTTP obtenus** (séquence `200, 201, 201, 422, 201, 200, 200, 404, 200, 422, 200, 200`) :

| Code | Nombre |
|------|--------|
| 200 | 6 |
| 201 | 3 |
| 404 | 1 |
| 422 | 2 |
| 5xx | **0** |

La seule différence avec le rejeu 0.3.0 est la cinquième requête (`urgent`), passée de
422 à 201, comme la spécification R2 (comportement requis 7) l'exige.

**Journal** : 128 lignes ajoutées à `var/log/dev.log` (lignes 5508 à 5635 : 63 lignes de
réinitialisation du schéma à 21:48:35-36, puis 65 lignes pour les douze requêtes à
21:48:38), dont **0 ligne `CRITICAL`**, 0 ligne `ERROR`, 0 ligne `WARNING`. La cinquième
requête écrit la ligne `app.INFO: Ticket created {"id":3,"priority":"urgent"}` (ligne
5612), comme toute création. Le fichier contient encore 40 lignes `CRITICAL`
historiques, toutes antérieures (dernière à 14:50:32 UTC, erreurs 500 fermées en 0.3.0,
voir A8) ; aucune n'est produite par ce rejeu.

Note : le volume `./var/log` est partagé avec le conteneur Docker ; le `healthcheck` du
conteneur `ticketflow-app-1`, observé en marche pendant le rejeu, écrit toutes les dix
secondes un bloc de sept lignes `GET http://127.0.0.1:8000/api/stats` (200) dans le même
fichier. Ces blocs encadrent celui du rejeu (lignes 5501-5507 avant, 5636 et suivantes
après) sans s'y mêler : les 128 lignes comptées portent toutes l'horodatage du rejeu
(voir A5).

Ce résultat est **identique** à celui consigné par le vérificateur de l'item R2
(`verdict-1.json` : `200:6 201:3 404:1 422:2, no 5xx, zero CRITICAL; fifth request logged
'Ticket created priority:urgent'`) et à la séquence attendue par R2 AC9. Le rejeu de
l'enregistreur ne contredit donc pas le verdict ; aucune anomalie bloquante n'est
ouverte à ce titre.

**Ce que ce rejeu prouve** : la section 6 de l'URS-002 (scénario standard sans 5xx)
après l'ajout de `urgent` ; l'acceptation de `urgent` sur `POST /api/tickets` (201) et sa
journalisation ; le maintien du 422 sur une `priority` absente ; l'absence de régression
de code HTTP sur les points d'entrée ticket et stats.

**Ce qu'il ne prouve pas** : la cible de 2 h (`dueAt`), prouvée uniquement par R2 AC1 ;
la ligne `urgent` de `GET /api/reports/sla` (R2 AC5, AC6, AC8), le point d'entrée
n'étant pas dans le scénario ; le rejet sensible à la casse (R2 AC4) ; la conservation
du `dueAt` des lignes antérieures (R2 AC8) ; les points d'entrée des items U1 à U5 de
0.3.0 ; le contenu JSON des réponses ; la migration (aucune dans la plage, et le
`--reset` recrée le schéma par `SchemaTool`) ; le trafic dans le conteneur (non rejoué
par l'enregistreur).

## 7. Traçabilité / Logs

**Plage de commits** : `36d2712..f05a245` sur la branche `build/urs-002` (premier commit
de la plage `16e098a`, dernier `f05a245`). Commit de code de l'item R2 : `1eac315`.
Commits d'infrastructure : `e6947a1`, `826a9a0` (Docker). Commit rejoué : `f05a245`
(`f05a245477caf7848b2e63fd23d96ab226cd6a84`, 2026-10-14 21:47:06 +0200). Diff applicatif
de la plage (`git diff --numstat 36d2712..HEAD -- src tests`) : 5 fichiers ;
`migrations/` : aucun fichier.

- `src/` (1 fichier) : `Entity/Ticket.php` (`+2 -2`). Revue de code du diff : seules les
  deux constantes changent, `PRIORITIES` (`'urgent'` ajouté en quatrième position) et
  `SLA_TARGET_HOURS` (`'urgent' => 2` ajouté) ; `STATUSES` et `SLA_DEFAULT_PRIORITY`
  intacts. Aucun autre fichier de `src/` ne figure dans le diff, conformément au
  périmètre « hors champ » de la spécification (DTO, `SlaReport`, repository,
  contrôleurs, écouteur, commande, `TicketStats`).
- `tests/` (4 fichiers) : `Api/R2UrgentPriorityTest.php` (nouveau, `+370`),
  `Api/F0SlaFoundationTest.php` (`+6 -6`), `Api/FXUrsNAcceptanceSweepTest.php`
  (`+12 -9`), `Api/U5SlaReportTest.php` (`+16 -11`). `Api/TicketApiTest.php`,
  `Api/ApiTestCase.php`, `bootstrap.php` et `Unit/F0SlaFoundationMigrationTest.php` :
  diff vide.
- Hors `src`/`tests` : `Dockerfile`, `compose.yaml`, `docker/entrypoint.sh`,
  `.dockerignore`, `.gitignore`, `README.md`, `CLAUDE.md`, `.claude/` (dix agents, trois
  skills, workflow), `approvals/` (README, cinq fichiers `approved/`),
  `reports/build-urs-002.md`, `reports/items/R2/`, `reports/items/U1/decisions.md`,
  `reports/items/FX/decisions.md`, `specs/R2-urgent-priority.md`,
  `release/unreleased/R2.md`, `requests/done/002-urgent-priority.md` (déplacé),
  `docs/user/tickets.md`, `docs/user/statistiques.md`, changelog. `composer.json`,
  `composer.lock`, `config/` : diff vide.

**Suite PHPUnit, hôte** (14 octobre 2026, 21:48 UTC+2, `f05a245`) :

```
tools/php vendor/bin/phpunit
PHPUnit 13.4.2 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.5
Configuration: D:\Sandbox\yt-30-10-2026\ticketflow\phpunit.dist.xml
...............................................................  63 / 126 ( 50%)
............................................................... 126 / 126 (100%)
Time: 00:07.148, Memory: 44.00 MB
OK (126 tests, 1302 assertions)
```

Répartition : `TicketApiTest` 3, `F0SlaFoundationTest` 13, `F0SlaFoundationMigrationTest` 2,
`R1FilterTicketsByStatusTest` 10, `U1AgentsCreateListTest` 16, `U2AgentActiveFlagTest` 18,
`U3TicketAssigneeTest` 21, `U4BreachedFilterTest` 11, `U5SlaReportTest` 10,
`FXUrsNAcceptanceSweepTest` 13, `R2UrgentPriorityTest` 9 (total 126, soit 117 + 9).
Aucun test en échec, ignoré, incomplet ou risqué ; aucune dépréciation signalée (la
ligne de résumé est `OK`, sans « issues »). Base de test : fichier
`var/data_test_<pid>.db` propre au processus (`tests/bootstrap.php`), supprimé en fin
d'exécution.

**Suite PHPUnit, conteneur** (14 octobre 2026, 21:50 UTC+2, `f05a245`, rejouée par
l'enregistreur) :

```
docker compose run --rm app test
PHPUnit 13.4.2 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.26
Configuration: /app/phpunit.dist.xml
...............................................................  63 / 126 ( 50%)
............................................................... 126 / 126 (100%)
Time: 00:10.043, Memory: 56.50 MB
OK (126 tests, 1302 assertions)
```

Résultat **rapporté** par l'orchestrateur pour la même commande, le 14 octobre 2026 :
`OK (126 tests, 1302 assertions)` ; concordant avec le rejeu ci-dessus. Le mode `test` de
`docker/entrypoint.sh` neutralise `DATABASE_URL` et force `APP_ENV=test`, de sorte que
`phpunit.dist.xml` et `.env.test` gouvernent la base de test dans le conteneur comme sur
l'hôte.

**Trafic simulé** (14 octobre 2026, 21:48 UTC+2, `f05a245`) :

```
tools/php bin/console app:simulate-traffic --reset
Schema reset: ticket table is empty.
GET    /api/stats                   -> 200
POST   /api/tickets                 -> 201
POST   /api/tickets                 -> 201
POST   /api/tickets                 -> 422
POST   /api/tickets                 -> 201
GET    /api/tickets                 -> 200
GET    /api/tickets/1               -> 200
GET    /api/tickets/9999            -> 404
PATCH  /api/tickets/1/status        -> 200
PATCH  /api/tickets/1/status        -> 422
GET    /api/tickets?status=open     -> 200
GET    /api/stats                   -> 200

HTTP 200: 6
HTTP 201: 3
HTTP 404: 1
HTTP 422: 2
Logs written to var/log/dev.log
```

Lignes ajoutées à `var/log/dev.log` : 128 (de la ligne 5508 à la ligne 5635) ; `grep -c
CRITICAL` sur ces lignes : 0 ; aucune ligne `ERROR` ni `WARNING` ; ligne 5612
`Ticket created {"id":3,"priority":"urgent"}` au niveau `info`.

**Migration** : non rejouée, la plage ne contenant aucune migration
(`git diff --name-only 36d2712..HEAD -- migrations` : vide ; `migrations/` contient
toujours `Version20261014130059.php` et `Version20261014133137.php`, rejouées pour
0.2.0). La spécification R2 (comportement requis 6) exclut toute migration : la colonne
`priority` (`length: 16`) accueille `urgent` sans changement de schéma.

**Tours du vérificateur** (un tour, `PASS`) :

| Item | Tour | Verdict | Suite au verdict | Notes du vérificateur (extraits) |
|------|------|---------|------------------|----------------------------------|
| R2 | 1 (`verdict-1.json`) | `PASS` | `OK (126 tests, 1302 assertions)` | AC1 à AC9 : une méthode chacun dans `R2UrgentPriorityTest.php` ; AC10 = les amendements prévus par le comportement requis 8 et par D3, tous présents et conformes ; `TicketApiTest` octet pour octet identique. URS-F-03.1/03.2/03.3, F-05.1/05.2, N-02, N-03 dans les noms et docblocks ; la demande 002 n'est nommée que dans le docblock de classe (style). Rejeu du trafic dans une base privée : `200:6 201:3 404:1 422:2`, aucun 5xx, zéro `CRITICAL` ; cinquième requête journalisée `Ticket created priority:urgent`. Diff : exactement les deux constantes de `src/Entity/Ticket.php`. Style : la formulation « index 3 » de la spécification était décalée d'un cran par rapport à sa propre liste AC9 ; le testeur a appliqué la liste, correctement (voir A1). `remaining_criticals_out_of_scope` : vide. Décisions : D1=B, D2=A, D3=A. |

**Rapport du testeur** (`tester.md`) : `tests/Api/R2UrgentPriorityTest.php` (9 méthodes),
critères couverts AC1 à AC10, non couverts : aucun ; amendements par RB8/AC10 (FX
`testAc2` renommé, `EXPECTED_STATUSES` index 4 `422` vers `201`, FX `testAc11`, F0
`testAc11`, U5 `KNOWN_PRIORITIES` + `testAc6` avec `escalated`) et par D3=A (U5
`testAc1` renommé, `testAc2` ligne `urgent` à zéro en fin de liste, `testAc4`,
`testAc10` `assertCount 4`), chacun avec une ligne de docblock citant R2 et la
décision ; `RUN RESULT: OK (126 tests, 1302 assertions)`, nouveau fichier seul
`OK (9 tests, 170 assertions)` ; `DECISIONS: D1=B, D2=A, D3=A`. Avant D3, le testeur
rapportait « suite 126 tests, 4 échecs, tous sur les assertions U5 à trois lignes »
(`approvals/approved/decision-R2-D3.md`, « Work so far » ; voir A7).

### Décisions humaines

Toutes les décisions de la plage ont une réponse d'une personne. Trois ont été remises
avant d'agir (R2 D1 à D3) ; deux, prises sans personne en 0.3.0, sont ratifiées après
coup dans cette plage (U1 D1, FX D1 ; voir A6).

| Id | Item / rôle | Question | Options | Décision | Qui | Quand | Fichier |
|----|-------------|----------|---------|----------|-----|-------|---------|
| R2 D1 | R2 / orchestrateur (demande 002 ; `ratify-FX-D1` avait maintenu le refus en attendant cette cible) | Ajouter `urgent` comme priorité de ticket, et avec quelle cible SLA ? | A. Continuer à refuser `urgent` (demande 002 reste ouverte) ; B. Ajouter `urgent` avec une cible donnée par une personne (nouvelle valeur acceptée, classée au-dessus de `high`, cible propre, ligne de rapport, test FX et docs amendés ; clôt la demande 002). Recommandation : aucune (valeur métier). | **B, cible 2 heures** - « urgent SLA target is 2 hours, close request 002 ». Remplace `ratify-FX-D1`. | Yi Shen | 2026-10-14 (chat, Claude Code Desktop) | `approvals/approved/decision-R2-D1.md` ; `reports/items/R2/decisions.md` |
| R2 D2 | R2 / spec-writer | Où placer `urgent` dans `Ticket::PRIORITIES`, donc dans l'ordre des lignes de `GET /api/reports/sla` ? | A. Ajouter après `high` : `low, normal, high, urgent` (sévérité croissante, index existants conservés, URS-N-03) ; B. Placer en tête : `urgent, low, normal, high` (toutes les lignes décalées, lecteurs positionnels cassés). Recommandation : A. | **A** - ajouter après `high` | Yi Shen | 2026-10-14 (chat, Claude Code Desktop) | `approvals/approved/decision-R2-D2.md` ; `reports/items/R2/decisions.md` |
| R2 D3 | R2 / développeur et testeur (soulevée indépendamment par les deux) | Le testeur peut-il amender quatre méthodes U5 supplémentaires (AC1, AC2, AC4, AC10) qui figent « exactement trois lignes », le rapport vide en ayant désormais quatre ? | A. Amender minimalement les quatre méthodes U5 (`assertCount(3)` vers 4, ligne `urgent` à zéro dans AC2 ; implémentation intacte ; spécification RB8/AC10 complétée) ; B. Les laisser échouer comme RB8 le dit littéralement (FAIL sans correction possible dans `src/`, item bloqué jusqu'à réécriture de la spécification) ; C. N'émettre la ligne `urgent` que si son total est positif (contredit AC5, URS-F-05.2 et D2 ; touche `SlaReport`, hors champ). Recommandation : A (les deux rôles). | **A** - amender les quatre assertions U5 à trois lignes | Yi Shen | 2026-10-14 (chat, Claude Code Desktop) | `approvals/approved/decision-R2-D3.md` ; `reports/items/R2/decisions.md` |
| U1 D1 (ratification) | U1 / orchestrateur (décision prise sans personne le 2026-10-14, en 0.3.0 ; dossier 0.3.0, A4) | Accepter l'ajout de `symfony/property-access` et `symfony/property-info` pour l'item U1 ? | A. Ratifier (conserver les deux composants ; `#[MapRequestPayload]` fonctionne ; U1, U2, U3, FX en dépendent) ; B. Revenir en arrière (retirer les composants, réécrire la liaison des requêtes à la main, rouvrir quatre items). Recommandation : A. | **A** - ratifiée comme recommandé | Yi Shen | 2026-10-14 (chat, Claude Code Desktop), après coup | `approvals/approved/ratify-U1-D1.md` ; `reports/items/U1/decisions.md` |
| FX D1 (ratification) | FX / développeur, confirmé par l'orchestrateur (décision prise sans personne le 2026-10-14, en 0.3.0 ; dossier 0.3.0, A6) | Refuser `urgent` (422) plutôt que l'ajouter, dans l'item FX ? | A. Ratifier (`urgent` reste refusé ; demande 002 ouverte jusqu'à ce qu'une personne fixe la cible) ; B. Revenir en arrière : ajouter `urgent` avec une cible donnée ici ; C. Revenir en arrière avec la cible `normal` de 24 h (contredit la demande). Recommandation : A. | **A** - ratifiée comme recommandé ; remplacée ensuite par R2 D1 = B | Yi Shen | 2026-10-14 (chat, Claude Code Desktop), après coup | `approvals/approved/ratify-FX-D1.md` ; `reports/items/FX/decisions.md` |

Aucune décision de la plage n'est dépourvue de réponse humaine ; aucune anomalie majeure
n'est ouverte à ce titre.

## 8. Anomalies détectées

| ID | Description | Gravité | Statut / Action |
|----|-------------|---------|-----------------|
| A1 | Spécification R2 : le comportement requis 8 nomme « exactement » cinq amendements et qualifie tout autre test en échec de défaut d'implémentation, alors que le comportement requis 5 / AC5 (ligne `urgent` présente même à zéro, D2, URS-F-05.2) fait nécessairement échouer quatre autres assertions U5 (« exactement trois lignes »). Contradiction interne détectée indépendamment par le développeur et le testeur, remise à une personne (D3 = A) et tranchée avant le verdict. La même spécification écrit « index 3 » pour le cinquième code de `EXPECTED_STATUSES` (décalage d'un cran, relevé par le vérificateur comme style). | Mineure | Résolue par D3 ; documentée. Compléter `specs/R2-urgent-priority.md` (RB8/AC10 : les quatre méthodes U5 amendées par décision ; « index 4 ») lors d'une prochaine révision ; aucune action sur le code. |
| A2 | Changement de contrat sur `POST /api/tickets`, pour la seconde fois en une journée : `urgent` était accepté avec 24 h en 0.2.0, refusé en 422 en 0.3.0 (FX AC2, dossier 0.3.0 A6), accepté avec 2 h en 0.4.0 (R2 AC1). Les autres valeurs hors liste restent refusées (R2 AC4). Changement voulu par décision humaine (R2 D1 = B) et conforme à la demande 002. | Mineure | Documentée. À signaler aux consommateurs de l'API avec le changement d'ordre des lignes du rapport SLA (quatrième ligne `urgent`, `other` en cinquième position). Ferme l'anomalie A6 du dossier 0.3.0 (demande 002 traitée). |
| A3 | Hétérogénéité des échéances des tickets `urgent` : les lignes créées avant 0.4.0 conservent `dueAt` `null` (migrées par F0) ou `createdAt` + 24 h (créées entre F0 et FX), tandis que les nouvelles reçoivent + 2 h (R2 AC8, URS-F-03.3). Dans le rapport SLA, les lignes à `dueAt` `null` ne sont jamais comptées dépassées. Comportement exigé par l'URS, sans recalcul ni migration. | Mineure | Documentée. Faire figurer dans le rapport officiel que la cible de 2 h ne vaut que pour les tickets créés à partir de 0.4.0 ; décider, le cas échéant, d'un item de recalcul distinct (décision humaine). |
| A4 | Le scénario standard de `app:simulate-traffic` n'exerce toujours pas `/api/reports/sla` (ni `/api/agents`, `/api/tickets/{id}/assignee`, `?breached=`) : la ligne `urgent` du rapport (R2 AC5, AC6, AC8) et la cible de 2 h (R2 AC1) ne sont prouvées que par PHPUnit. Reconduction de l'anomalie A7 du dossier 0.3.0. | Mineure | Ouverte (voir « Périmètre non couvert »). Étendre le scénario du simulateur au module SLA lors d'un item ultérieur. |
| A5 | Conteneur Docker (`Dockerfile`, `compose.yaml`, `docker/entrypoint.sh`) livré sans spécification ni verdict de vérificateur. Preuves disponibles : suite `OK (126 tests, 1302 assertions)` rapportée par l'orchestrateur et rejouée par l'enregistreur (PHP 8.4.26 dans l'image contre 8.4.5 sur l'hôte). Le mode `serve` n'est pas exercé. Effet de bord observé : le `healthcheck` du conteneur en marche écrit un bloc `GET /api/stats` toutes les dix secondes dans `var/log/dev.log`, partagé par volume avec l'hôte, ce qui mêle du trafic de supervision aux lignes lues par l'agent log-analyst et par ce dossier. | Mineure | Documentée. Rapporter dans le rapport officiel que la validation est faite sur PHP 8.4.5 (hôte) et 8.4.26 (image) ; envisager un canal de journal distinct ou un `healthcheck` silencieux pour le conteneur. |
| A6 | Deux décisions de 0.3.0 avaient été prises sans réponse d'une personne : U1 D1 (dépendances `symfony/property-access`, `symfony/property-info` ; dossier 0.3.0 A4) et FX D1 (`urgent` refusé en 422 ; dossier 0.3.0 A6). Au sens du protocole de décision introduit dans cette plage, une décision sans réponse humaine est une anomalie majeure. Les deux sont **ratifiées après coup** par Yi Shen le 14 octobre 2026 (`approvals/approved/ratify-U1-D1.md`, `ratify-FX-D1.md`, commit `c1103c7`) et FX D1 est ensuite remplacée par R2 D1 = B. | Mineure (majeure à l'origine, régularisée) | Fermée par ratification. Le protocole de décision (`CLAUDE.md`, « Decision protocol ») et la gate `approvals/` visent à empêcher la récurrence ; les trois décisions de R2 ont été remises avant d'agir. |
| A7 | Chronologie des preuves : avant D3, la suite complète comptait 4 échecs (assertions U5 « trois lignes »), conséquence directe de D2 et d'URS-F-05.2, non d'un défaut d'implémentation. Le verdict (`PASS`, `OK (126 tests, 1302 assertions)`) et le rejeu de l'enregistreur attestent le passage au commit `1eac315` et à `f05a245`. | Mineure | Sans action. Mentionnée pour que la chronologie soit complète : la preuve de R2 AC10 repose sur le verdict et le rejeu, après D3. |
| A8 | **Antérieur à cette version.** `var/log/dev.log` contient encore 40 lignes `CRITICAL` historiques (`DivisionByZeroError` dans `TicketStats.php`, `Undefined array key "priority"` et `toArray() on null` dans `TicketController.php`), toutes datées au plus tard du 14 octobre 2026 à 14:50:32 UTC, soit avant FX ; les causes sont fermées en 0.3.0 (A8 à A10 du dossier 0.3.0). Aucune ligne `CRITICAL` n'est produite par le rejeu de ce dossier. | Mineure (antérieure) | Sans action sur le code. Le fichier de journal n'est pas purgé entre versions ; un lecteur du journal brut doit filtrer par date. |

Synthèse : 8 anomalies, 0 bloquante, 0 majeure, 8 mineures (A1 à A8), dont trois fermées
ou résolues (A1 par D3, A6 par ratification, A8 antérieure) et cinq documentées (A2 à
A5, A7).

## 9. Conclusion

**La version 0.4.0 est validée au commit `f05a245`.**

Les dix critères d'acceptation de l'item R2 sont chacun couverts par au moins un test
rejoué par l'enregistreur le 14 octobre 2026, et la suite complète est verte sur l'hôte
(`OK (126 tests, 1302 assertions)`, PHP 8.4.5) comme dans le conteneur Docker
(`OK (126 tests, 1302 assertions)`, PHP 8.4.26), sans dépréciation. Le rejeu du trafic
simulé donne `200:6 201:3 404:1 422:2`, **aucun 5xx et zéro `CRITICAL`**, résultat
strictement identique à celui consigné par le vérificateur de l'item R2 et à la séquence
exigée par R2 AC9 ; la seule différence avec 0.3.0 est le 201 attendu sur `urgent`. Le
diff applicatif se limite aux deux constantes de `src/Entity/Ticket.php` ; la plage ne
contient aucune migration. Le verdict (un tour `PASS`) est cohérent avec le rejeu. Les
trois décisions de l'item (D1 à D3) et les deux ratifications (U1 D1, FX D1) portent
chacune la réponse d'une personne. Les deux seules clauses sans test (exclusivité des
amendements, intégrité de `TicketApiTest`) sont établies par revue du diff et n'entament
pas la validation.

Points d'attention avant la validation finale et pour les versions suivantes :

1. Signaler aux consommateurs de l'API le nouveau contrat de `POST /api/tickets`
   (`urgent` accepté, cible 2 h) et la quatrième ligne de `GET /api/reports/sla` (A2),
   ainsi que le fait que les tickets `urgent` antérieurs gardent leur échéance d'origine
   (A3) ; décider s'il faut un item de recalcul.
2. Faire figurer dans le rapport officiel les deux environnements d'exécution de la
   suite (PHP 8.4.5 sur l'hôte, 8.4.26 dans l'image) et l'absence de preuve sur le mode
   `serve` du conteneur (A5).
3. Étendre le scénario de `app:simulate-traffic` au module SLA (A4) pour disposer d'une
   preuve end-to-end de la ligne `urgent` du rapport et de la cible de 2 h.
4. Corriger la spécification R2 (A1 : RB8/AC10 et « index 3 ») et, à l'occasion, isoler
   le journal du `healthcheck` du conteneur (A5).

Ce dossier est un projet complémentaire au rapport de validation officiel ; il ne s'y
substitue pas et ne vaut pas, à lui seul, autorisation de mise en production.
