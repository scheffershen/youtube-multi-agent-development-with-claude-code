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

## Répartition par priorité

La réponse contient aussi `by_priority`, un objet toujours présent dont les clés sont
exactement `low`, `normal`, `high`, `urgent` et `other`. Toutes les valeurs sont des
entiers, égaux à 0 lorsqu'aucun ticket ne correspond.

| Clé | Description |
|-----|-------------|
| `low`, `normal`, `high`, `urgent` | Nombre de tickets ayant exactement cette priorité |
| `other` | Nombre de tickets dont la priorité n'est pas l'une des quatre précédentes |

La somme des cinq valeurs est toujours égale à `total`. Les champs existants ne changent
pas.

## Exemple

Avec 2 tickets dont 1 clôturé, la réponse indique `total` à 2, `open` à 1, `closed` à 1 et
`closed_ratio` à 50.

## Aucun ticket

Lorsqu'aucun ticket n'existe, la réponse est `200` et tous les compteurs valent 0 :

```json
{ "total": 0, "open": 0, "in_progress": 0, "closed": 0, "closed_ratio": 0,
  "by_priority": { "low": 0, "normal": 0, "high": 0, "urgent": 0, "other": 0 } }
```

`closed_ratio` reste un nombre (0 ou 0.0), jamais `null` ni du texte. Dès qu'au moins un
ticket existe, les valeurs sont calculées comme décrit ci-dessus.
