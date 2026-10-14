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
