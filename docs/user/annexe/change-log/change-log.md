---
sidebar_position: 1
id: change-log
title: Changelog
---

## Historique des versions de l'application

### Version 0.2.0 - 14 octobre 2026

**Améliorations techniques**

- **SLA — Socle de persistance (agents, affectation, échéance)** : le schéma gagne une table `agent` et deux colonnes sur `ticket`, et chaque ticket créé reçoit une échéance calculée à partir de sa priorité ; aucun nouvel endpoint.
  - Migration `Version20261014133137` : nouvelle table `agent` (`id`, `name` VARCHAR(120), `email` VARCHAR(180) avec index unique, `active` BOOLEAN) ; colonnes `due_at DATETIME DEFAULT NULL` et `assignee_id INTEGER DEFAULT NULL` ajoutées à `ticket`, clé étrangère `assignee_id -> agent(id) ON DELETE SET NULL` et index sur `assignee_id`.
  - Entité `App\Entity\Agent` (`name`, `email`, `active` à `true` par défaut, `toArray()` avec `id`, `name`, `email`, `active`) et `AgentRepository` ; sur `Ticket`, relation `assignee` (`ManyToOne` nullable, `getAssignee()` / `setAssignee(?Agent)`) et champ `dueAt` en lecture seule (`getDueAt()`, pas de `setDueAt()`).
  - Cibles SLA : constante `Ticket::SLA_TARGET_HOURS` (`low` 72 h, `normal` 24 h, `high` 8 h) et `Ticket::slaTargetHours(string)` ; toute priorité hors de cette liste (`urgent`, chaîne vide, `LOW`) retombe sur `normal`, soit 24 h. L'échéance vaut `createdAt + N heures`, calculée une seule fois dans le constructeur et jamais recalculée ensuite.
  - Données et appelants existants : les lignes `ticket` antérieures à la migration sont conservées à l'identique avec `assignee_id` et `due_at` à `NULL` ; `Ticket::__construct` garde ses trois paramètres dans le même ordre et gagne deux paramètres optionnels (`createdAt`, `dueAt`) ; `GET /api/stats` est inchangé.
  - Tests : classe de base `tests/Api/ApiTestCase` (`postJson()`, `patchJson()`, `body()`, `createAgent()`, `createTicket()`) ; `TicketApiTest` s'appuie dessus sans changer ses assertions.
  - **Tickets — Nouvelles clés JSON** : l'objet ticket renvoyé par `POST /api/tickets`, `GET /api/tickets`, `GET /api/tickets/{id}` et `PATCH /api/tickets/{id}/status` contient désormais `dueAt` (chaîne ISO 8601 `DATE_ATOM`, même format que `createdAt` ; `null` pour les tickets antérieurs à la migration) et `assignee` (toujours `null` dans cette version).
  - **Impact API** : deux clés ajoutées en fin d'objet ticket, `dueAt` et `assignee` ; aucune clé renommée ou supprimée, codes de statut inchangés.

### Version 0.1.0 - 14 octobre 2026

**Nouvelles fonctionnalités**

- **Tickets — API de base** : Création, liste, consultation et changement de statut des tickets.
  - `POST /api/tickets` crée un ticket avec `title`, `description`, `priority` ; statut `open` à la création.
  - `GET /api/tickets` renvoie `items` et `count`, du plus récent au plus ancien.
  - `PATCH /api/tickets/{id}/status` accepte `open`, `in_progress`, `closed` ; `422` sinon, `404` si le ticket n'existe pas.
- **Statistiques — Vue d'ensemble** : `GET /api/stats` renvoie les compteurs par statut et `closed_ratio`.

**Améliorations techniques**

- **Base de données — Schéma initial** : table `ticket` créée par la migration `Version20261014130059`. Base SQLite en développement et en test.
- **Outillage — Simulation de trafic** : commande `app:simulate-traffic` rejouant un scénario d'appels réalistes à travers le noyau Symfony pour alimenter `var/log/dev.log` ; option `--reset` pour repartir d'une table vide.
