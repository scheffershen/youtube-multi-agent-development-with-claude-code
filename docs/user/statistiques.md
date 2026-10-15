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

## Exemple

Avec 2 tickets dont 1 clôturé, la réponse indique `total` à 2, `open` à 1, `closed` à 1 et
`closed_ratio` à 50.

## Aucun ticket

Lorsqu'aucun ticket n'existe, la réponse est `200` et tous les compteurs valent 0 :

```json
{ "total": 0, "open": 0, "in_progress": 0, "closed": 0, "closed_ratio": 0 }
```

`closed_ratio` reste un nombre (0 ou 0.0), jamais `null` ni du texte. Dès qu'au moins un
ticket existe, les valeurs sont calculées comme décrit ci-dessus.
