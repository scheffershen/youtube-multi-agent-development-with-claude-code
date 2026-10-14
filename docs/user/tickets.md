---
sidebar_position: 2
id: tickets
title: Tickets
---

# Tickets

Un ticket représente une demande d'assistance. Il porte un titre, une description, une
priorité et un statut.

## Champs d'un ticket

| Champ | Type | Description |
|-------|------|-------------|
| `id` | entier | Identifiant attribué à la création |
| `title` | texte (120 caractères max.) | Titre court de la demande |
| `description` | texte | Détail de la demande |
| `priority` | `low`, `normal`, `high` | Priorité déclarée à la création |
| `status` | `open`, `in_progress`, `closed` | Statut courant, `open` à la création |
| `createdAt` | date ISO 8601 | Date et heure de création |
| `dueAt` | date ISO 8601 ou `null` | Échéance de traitement, fixée à la création selon la priorité (voir [Échéance de traitement](#échéance-de-traitement)) |
| `assignee` | `null` | Agent affecté au ticket ; toujours `null` pour l'instant |

## Échéance de traitement

À la création, chaque ticket reçoit une échéance (`dueAt`) égale à sa date de création
augmentée d'un délai cible qui dépend de la priorité déclarée :

| Priorité | Délai cible |
|----------|-------------|
| `low` | 72 heures |
| `normal` | 24 heures |
| `high` | 8 heures |

Toute autre valeur de priorité reçoit le délai de `normal`, soit 24 heures. La
correspondance est sensible à la casse : `LOW` n'est pas reconnu comme `low`.

L'échéance est enregistrée une fois pour toutes à la création et n'est jamais
recalculée. Elle est renvoyée au format ISO 8601, comme `createdAt`, et prend la même
valeur dans toutes les réponses qui décrivent le ticket (création, liste, consultation,
changement de statut).

**Note sur la visibilité** : les tickets créés avant l'introduction de cette échéance
n'en ont pas ; leur champ `dueAt` vaut `null`.

## Créer un ticket

`POST /api/tickets`

Corps attendu :

```json
{ "title": "Page de connexion vide", "description": "Depuis la mise à jour, Safari n'affiche rien.", "priority": "high" }
```

Réponse `201` avec le ticket créé.

## Lister les tickets

`GET /api/tickets`

Renvoie `{ "items": [...], "count": n }`, du plus récent au plus ancien.

## Consulter un ticket

`GET /api/tickets/{id}`

Renvoie le ticket demandé.

## Changer le statut

`PATCH /api/tickets/{id}/status`

Corps attendu : `{ "status": "in_progress" }`. Les valeurs acceptées sont `open`,
`in_progress` et `closed`.

### Erreurs

| Code | Signification |
|------|---------------|
| `404` | Le ticket n'existe pas |
| `422` | Le statut demandé n'est pas dans la liste autorisée ; la réponse indique les valeurs acceptées |
