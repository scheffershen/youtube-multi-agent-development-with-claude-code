---
sidebar_position: 3
id: statistiques
title: Statistiques
---

# Statistiques

`GET /api/stats`

Renvoie le nombre de tickets par statut et la part de tickets clôturés.

| Champ | Type | Description |
|-------|------|-------------|
| `total` | entier | Nombre total de tickets |
| `open` | entier | Tickets au statut `open` |
| `in_progress` | entier | Tickets au statut `in_progress` |
| `closed` | entier | Tickets au statut `closed` |
| `closed_ratio` | nombre | Part des tickets clôturés, en pourcentage arrondi à une décimale |
