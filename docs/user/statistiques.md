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

La réponse `200` contient exactement ces cinq champs, dans cet ordre.

`closed_ratio` vaut `closed` divisé par `total`, multiplié par 100 et arrondi à une
décimale : 1 ticket clôturé sur 3 donne `33.3`.

Sans aucun ticket, la réponse est `200` avec les quatre compteurs à `0` et `closed_ratio`
à `0` ; aucune division n'est effectuée dans ce cas.

## Rapport SLA par priorité

`GET /api/reports/sla`

Renvoie, pour chaque priorité, le nombre de tickets, le nombre de tickets en dépassement
d'échéance et le taux de dépassement. L'appel ne prend aucun paramètre.

Réponse `200` :

```json
{
  "generatedAt": "2026-10-14T10:00:00+00:00",
  "priorities": [
    { "priority": "low", "total": 0, "breached": 0, "breach_rate": 0.0 },
    { "priority": "normal", "total": 3, "breached": 1, "breach_rate": 33.3 },
    { "priority": "high", "total": 1, "breached": 1, "breach_rate": 100.0 },
    { "priority": "urgent", "total": 0, "breached": 0, "breach_rate": 0.0 }
  ]
}
```

La réponse contient exactement ces deux champs, dans cet ordre :

| Champ | Type | Description |
|-------|------|-------------|
| `generatedAt` | date ISO 8601 | Instant auquel le rapport a été évalué, au même format que `createdAt` et `dueAt` d'un ticket |
| `priorities` | liste | Une entrée par priorité, dans l'ordre `low`, `normal`, `high`, `urgent`, suivie le cas échéant d'une entrée `other` (voir [Priorités inconnues](#priorités-inconnues)) |

Chaque entrée de `priorities` contient exactement ces quatre champs, dans cet ordre :

| Champ | Type | Description |
|-------|------|-------------|
| `priority` | texte | Priorité concernée |
| `total` | entier | Nombre de tickets de cette priorité, quel que soit leur statut |
| `breached` | entier | Nombre de ces tickets en dépassement d'échéance à l'instant `generatedAt` |
| `breach_rate` | nombre | Taux de dépassement, en pourcentage arrondi à une décimale |

### Définitions

Un seul instant est retenu pour toute la requête : il sert au calcul de chaque entrée et
est renvoyé dans `generatedAt`.

`total` compte tous les tickets dont la priorité est celle de l'entrée, quel que soit
leur statut. `breached` compte ceux de ces tickets qui sont en dépassement d'échéance à
cet instant, selon la même définition que le filtre `breached` de la liste des tickets
(voir [Filtrer par dépassement d'échéance](./tickets.md#filtrer-par-dépassement-déchéance)) :
statut autre que `closed`, échéance `dueAt` renseignée et strictement antérieure à
l'instant d'évaluation. Un ticket `closed` dont l'échéance est passée, un ticket sans
échéance (`dueAt` à `null`) et un ticket dont l'échéance n'est pas encore atteinte
comptent donc dans `total` mais pas dans `breached` ; `breached` est toujours compris
entre `0` et `total`.

`breach_rate` vaut `breached` divisé par `total`, multiplié par 100 et arrondi à une
décimale (l'arrondi se fait à la valeur la plus éloignée de zéro) : 1 ticket sur 3 donne
`33.3`, 2 sur 3 donnent `66.7`, 1 sur 6 donne `16.7`, 1 sur 2 donne `50.0`, 1 sur 1
donne `100.0`. Il est toujours renvoyé comme un nombre JSON avec une partie décimale
(`0.0`, `50.0`, `100.0`, jamais `0`, `50` ni `"50.0"`).

Une priorité sans aucun ticket est tout de même listée, avec l'entrée
`{ "priority": "<p>", "total": 0, "breached": 0, "breach_rate": 0.0 }` ; aucune division
n'est effectuée dans ce cas. Sans aucun ticket, la réponse est `200` avec les quatre
entrées `low`, `normal`, `high` et `urgent` à zéro ; `priorities` contient alors
exactement quatre éléments et aucune valeur n'est `null`.

### Priorités inconnues

Les tickets dont la priorité n'est pas `low`, `normal`, `high` ou `urgent` ne sont pas
ignorés : ils sont regroupés, quelle que soit leur valeur de priorité, dans une entrée
supplémentaire `{ "priority": "other", "total": n, "breached": m, "breach_rate": r }`,
calculée selon les mêmes règles et placée après les priorités connues ; elle est donc
toujours la dernière de la liste. Cette entrée n'apparaît que lorsque son `total` est
d'au moins `1` : si tous les tickets ont une priorité connue, `priorities` contient
exactement quatre éléments.

**Note sur la visibilité** : les tickets de priorité `urgent` créés avant que cette
priorité ne soit acceptée à la création sont comptés dans l'entrée `urgent`, et non dans
`other`. Ceux qui n'ont pas d'échéance (`dueAt` à `null`) comptent dans `total` mais
jamais dans `breached`.

La somme des `total` est ainsi toujours égale au nombre de tickets, et la somme des
`breached` est égale au `count` de `GET /api/tickets?breached=true` pris au même instant.

### Paramètres de requête

Le rapport ne lit aucun paramètre de requête ni aucun corps. Toute chaîne de requête,
y compris des valeurs que d'autres appels refusent (`?breached=maybe`, `?status=done`) ou
des valeurs non scalaires (`?priority[]=low`), est ignorée : la réponse est la même
`200` qu'un appel sans paramètre, avec des `priorities` identiques ; seul `generatedAt`
peut différer du temps écoulé.

### Erreurs

Toutes les erreurs sont renvoyées en JSON.

| Code | Signification |
|------|---------------|
| `405` | La méthode n'est pas `GET` (`POST`, `PUT`, `PATCH` ou `DELETE`) ; la réponse contient une clé `error` et l'en-tête `Allow` indique `GET` |
