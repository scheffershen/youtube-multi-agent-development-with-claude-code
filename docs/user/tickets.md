---
sidebar_position: 2
id: tickets
title: Tickets
---

# Tickets

Un ticket représente une demande d'assistance. Il porte un titre, une description, une
priorité et un statut, et peut être affecté à un agent.

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
| `assignee` | objet ou `null` | Agent affecté au ticket : `{ "id": <entier>, "name": "<nom>" }`, ou `null` si aucun agent n'est affecté (voir [Agent affecté](#agent-affecté)) |

Les réponses qui décrivent un ticket contiennent exactement ces huit champs, dans cet
ordre.

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

## Agent affecté

Le champ `assignee` indique l'agent affecté au ticket. Il vaut `null` tant qu'aucun
agent n'est affecté, et sinon un objet à deux clés, dans cet ordre :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | entier | Identifiant de l'agent |
| `name` | texte | Nom de l'agent |

L'adresse e-mail et l'indicateur d'activité de l'agent n'y figurent pas ; consultez la
page [Agents](./agents.md) pour ces informations.

Ce champ est présent dans toutes les réponses qui décrivent un ticket : création
(toujours `null`, un ticket est créé sans agent), liste (chaque élément), consultation,
changement de statut et affectation.

**Note sur la visibilité** : un agent désactivé après son affectation reste visible
comme agent affecté ; la désactivation d'un agent ne retire pas ses tickets.

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

### Filtrer par statut

Vous pouvez restreindre la liste aux tickets d'un statut donné avec le paramètre de
requête `status` :

`GET /api/tickets?status=open`

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `status` | `open`, `in_progress`, `closed` | non | Ne renvoie que les tickets dont le statut est exactement cette valeur |

Sans le paramètre, la liste complète est renvoyée, comme auparavant. Avec le paramètre,
la réponse garde la même forme (`items` et `count`), le même ordre du plus récent au
plus ancien et les mêmes champs par ticket ; `count` est le nombre de tickets renvoyés.
Si aucun ticket ne correspond, la réponse est `200` avec `{ "items": [], "count": 0 }`.

La valeur est comparée exactement, en respectant la casse : `Open` ou `OPEN` ne sont pas
reconnus comme `open`, et une valeur vide ou entourée d'espaces est refusée.

### Erreurs

| Code | Signification |
|------|---------------|
| `400` | La valeur de `status` n'est pas dans la liste autorisée (valeur inconnue, vide, casse différente, espaces, ou valeur non scalaire telle que `status[]=open`) ; la réponse JSON contient `"error": "Invalid status"` et la liste des valeurs acceptées dans `allowed` |

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

## Affecter ou désaffecter un ticket

`PATCH /api/tickets/{id}/assignee`

La requête est envoyée avec l'en-tête `Content-Type: application/json`.

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `agentId` | entier ou `null` | oui | Identifiant d'un agent actif pour affecter le ticket, ou `null` pour retirer l'agent affecté |

Les clés inconnues du corps sont ignorées.

### Affecter un agent

Corps attendu :

```json
{ "agentId": 1 }
```

L'agent doit exister et être actif. Réponse `200` avec le ticket mis à jour, dont le
champ `assignee` décrit l'agent :

```json
{ "id": 7, "title": "Page de connexion vide", "description": "Depuis la mise à jour, Safari n'affiche rien.", "priority": "high", "status": "open", "createdAt": "2026-10-14T13:01:02+00:00", "dueAt": "2026-10-14T21:01:02+00:00", "assignee": { "id": 1, "name": "Ada" } }
```

Si le ticket est déjà affecté à un autre agent, celui-ci est remplacé. Affecter de
nouveau l'agent déjà affecté renvoie `200` avec le même ticket.

### Retirer l'agent affecté

Corps attendu :

```json
{ "agentId": null }
```

Réponse `200` avec le ticket mis à jour, dont le champ `assignee` vaut `null`. La
requête réussit aussi lorsque le ticket n'avait pas d'agent affecté.

Envoyer un corps sans la clé `agentId` (par exemple `{}`) ne retire pas l'agent : la
requête est refusée (voir ci-dessous).

### Erreurs

Toutes les erreurs sont renvoyées en JSON. Lorsqu'une requête est refusée, le ticket
n'est pas modifié : l'agent affecté précédemment, ou l'absence d'agent, est conservé.

| Code | Signification |
|------|---------------|
| `404` | Le ticket n'existe pas ; la réponse est `{ "error": "Ticket not found" }`. Un identifiant de ticket non numérique renvoie aussi `404` avec `{ "error": "<message>" }` |
| `404` | Aucun agent ne porte l'identifiant `agentId` (y compris `0` ou un entier négatif) ; la réponse est exactement `{ "error": "Agent not found" }` |
| `422` | L'agent existe mais est inactif ; la réponse est exactement `{ "errors": { "agentId": "Agent is not active." } }` |
| `422` | La clé `agentId` est absente du corps, ou présente mais ni entier ni `null` (par exemple `"abc"`, `"5"`, `true` ou `1.5`) ; la réponse est `{ "errors": { "agentId": "<message>" } }` |
| `400` | Le corps n'est pas du JSON valide ou est vide ; la réponse est `{ "error": "<message>" }` |
| `415` | Le corps est envoyé avec un `Content-Type` autre que JSON, par exemple `text/plain` ; la réponse est `{ "error": "<message>" }` |

Les contrôles sont effectués dans cet ordre : forme du corps (`415`, `400`, `422`),
puis existence du ticket (`404`), puis, si `agentId` n'est pas `null`, existence et
activité de l'agent (`404`, `422`). Avec `agentId` à `null`, aucun contrôle d'agent n'a
lieu.
