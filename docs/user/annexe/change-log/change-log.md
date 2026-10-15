---
sidebar_position: 1
id: change-log
title: Changelog
---

## Historique des versions de l'application

### Version 0.2.0 - 15 octobre 2026

**Nouvelles fonctionnalités**

- **Tickets — Priorité `urgent` et validation des priorités** : `POST /api/tickets` accepte désormais la priorité `urgent` (rang le plus élevé) et rejette toute priorité inconnue ; `GET /api/stats` expose la répartition par priorité.
  - `Ticket::PRIORITIES` vaut `['low', 'normal', 'high', 'urgent']`, dans cet ordre ; aucun tri ni filtre par priorité n'est ajouté à `GET /api/tickets`.
  - Une `priority` hors de ces quatre valeurs (comparaison exacte, sensible à la casse : `HIGH` est refusée) renvoie `422` avec `{"errors": {"priority": "<message>"}}` ; aucun ticket n'est enregistré. Une `priority` absente ou vide renvoie toujours `422` avec `errors.priority`.
  - `GET /api/stats` ajoute `by_priority` : objet toujours présent avec les clés entières `low`, `normal`, `high`, `urgent` et `other` (0 si aucun ticket). `other` compte les tickets dont la priorité est hors liste ; les cinq valeurs totalisent `total`.
  - Les tickets existants ne sont ni modifiés ni supprimés, sans migration : un ticket dont la priorité est hors liste reste renvoyé tel quel par `GET /api/tickets` et `GET /api/tickets/{id}`. Les clés existantes de `/api/stats` (`total`, `open`, `in_progress`, `closed`, `closed_ratio`) sont inchangées.
  - **Impact API** : `POST /api/tickets` renvoie `422` pour une `priority` non vide qui était auparavant stockée (par exemple `critical`) ; `urgent` renvoie `201`. La réponse de `GET /api/stats` gagne la clé `by_priority`.
- **Tickets — Filtre de la liste par statut** : `GET /api/tickets?status=<statut>` ne renvoie que les tickets dont le `status` correspond, et répond `400` pour un statut inconnu.
  - Valeurs acceptées : `open`, `in_progress`, `closed` (issues de `Ticket::STATUSES`) ; la comparaison est sensible à la casse, `OPEN` est refusé.
  - Réponse inchangée dans sa forme : `{"items": [...], "count": N}`, du plus récent au plus ancien ; un statut valide sans ticket correspondant renvoie `200` avec `{"items": [], "count": 0}`.
  - Statut inconnu ou valeur vide (`?status=`) : `400` avec `{"errors": {"status": "<message>"}}` ; le message nomme les valeurs autorisées et la réponse ne contient pas de clé `items`.
  - Le filtrage est exécuté par le dépôt (`TicketRepository::findAllNewestFirst(?string $status)`) et non en mémoire.
  - Inchangé : sans paramètre `status`, tous les tickets sont renvoyés ; `POST /api/tickets` répond toujours `422` (table `errors`) et `PATCH /api/tickets/{id}/status` conserve son format `error` / `allowed`.
  - **Impact API** : `GET /api/tickets` accepte le paramètre `status` (jusqu'ici ignoré) et renvoie `400` avec le corps `errors.status` lorsque sa valeur est inconnue ou vide.

**Corrections de bugs**

- **Statistiques — `GET /api/stats` sans ticket** : la route répond désormais `200` avec des compteurs à zéro lorsque la table `ticket` est vide, au lieu d'une erreur `500`.
  - Corps renvoyé pour une table vide : `total`, `open`, `in_progress`, `closed` à `0` et `closed_ratio` à `0.0` (valeur numérique, ni `null` ni chaîne).
  - Cause corrigée : division par zéro (`DivisionByZeroError`) dans `TicketStats::summary()` lors du calcul de `closed_ratio` ; le calcul est protégé pour `$total === 0`.
  - Inchangé dès qu'il existe au moins un ticket : mêmes clés, mêmes compteurs, `closed_ratio` reste le pourcentage de tickets `closed` arrondi à une décimale (2 tickets dont 1 `closed` : `50`).
  - Aucune modification de la route, des clés JSON, du schéma ni migration.
  - **Impact API** : `GET /api/stats` sur une table vide passe de `500` à `200` ; la forme de la réponse pour les cas non vides ne change pas.
- **Tickets — Création sans champ obligatoire** : `POST /api/tickets` renvoie désormais `422` au lieu de `500` lorsque `title`, `description` ou `priority` est absent, `null` ou vide.
  - Le corps de la réponse est `{"errors": {"<champ>": "<message>"}}` : une table plate champ vers un seul message (le premier), avec tous les champs en échec dans la même réponse.
  - Un corps `{}` ou vide renvoie `422` avec exactement les clés `title`, `description` et `priority`.
  - Aucun ticket n'est enregistré lors d'un `422`.
  - Une valeur de `priority` non vide mais inconnue (par exemple `"bogus"`) reste acceptée (`201`) ; seule la présence est contrôlée.
  - Inchangé : un corps complet renvoie toujours `201` avec `id`, `title`, `description`, `priority`, `status` (`open`) et `createdAt` ; `PATCH /api/tickets/{id}/status` conserve son format d'erreur `error` / `allowed`.
  - **Impact API** : `POST /api/tickets` avec un champ obligatoire manquant passe de `500` à `422` avec le corps `errors`.
- **Tickets — Consultation d'un ticket inexistant** : `GET /api/tickets/{id}` renvoie désormais `404` au lieu de `500` lorsque aucun ticket ne correspond à l'identifiant.
  - Le corps de la réponse est `{"error": "Ticket not found"}` avec un type de contenu JSON : même statut et même corps que le cas « ticket inconnu » de `PATCH /api/tickets/{id}/status`.
  - La clé est `error` (chaîne), et non la table `errors` utilisée par `POST /api/tickets` pour les `422`, car l'erreur n'est liée à aucun champ.
  - Aucune écriture en base n'est effectuée pour un identifiant inconnu.
  - Inchangé : pour un ticket existant, la réponse reste `200` avec `id`, `title`, `description`, `priority`, `status` et `createdAt` ; la route conserve la contrainte `\d+`.
  - **Impact API** : `GET /api/tickets/{id}` avec un identifiant inconnu passe de `500` à `404` avec le corps `error`.

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
