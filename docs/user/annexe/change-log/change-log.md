---
sidebar_position: 1
id: change-log
title: Changelog
---

## Historique des versions de l'application

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
